<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AutoCrudController extends Controller
{
    /* ===================== HELPER SCHEMA ===================== */

    protected function modelClass(Request $request): string
    {
        return $request->route()->getAction('modelClass');
    }

    protected function table(string $modelClass): string
    {
        return (new $modelClass)->getTable();
    }

    protected function doctrineTable(string $table)
    {
        $schema = Schema::getConnection()->getDoctrineSchemaManager();
        $schema->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        return $schema->listTableDetails($table);
    }

    protected function doctrineColumns(string $table): array
    {
        $t = $this->doctrineTable($table);
        $cols = [];
        foreach ($t->getColumns() as $c) {
            $cols[$c->getName()] = [
                'type'      => $c->getType()->getName(), // string, integer, boolean, date, datetime, text, decimal, json, ...
                'length'    => $c->getLength(),
                'notnull'   => $c->getNotnull(),
                'precision' => method_exists($c, 'getPrecision') ? $c->getPrecision() : null,
                'scale'     => method_exists($c, 'getScale') ? $c->getScale() : null,
            ];
        }
        return $cols;
    }

    protected function columns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /**
     * Bangun rule dasar dari tipe kolom (tanpa FK/unique).
     * Return format: ['field' => ['required','string','max:255', ...]]
     */
    protected function baseRules(string $table, array $options = [], bool $forUpdate = false): array
    {
        $cols   = $this->doctrineColumns($table);
        $ignore = array_merge(['id', 'created_at', 'updated_at', 'deleted_at'], $options['except'] ?? []);
        $target = $options['only'] ? array_fill_keys($options['only'], true) : array_fill_keys(array_keys($cols), true);

        $rules = [];
        foreach ($cols as $name => $meta) {
            if (!isset($target[$name]) || in_array($name, $ignore, true)) continue;

            $r = [];
            $r[] = $forUpdate ? 'sometimes' : ($meta['notnull'] ? 'required' : 'nullable');

            switch ($meta['type']) {
                case 'integer':
                case 'bigint':
                case 'smallint':
                    $r[] = 'integer';
                    break;
                case 'decimal':
                case 'float':
                case 'double':
                case 'real':
                case 'numeric':
                    $r[] = 'numeric';
                    break;
                case 'boolean':
                    $r[] = 'boolean';
                    break;
                case 'datetime':
                case 'datetimetz':
                case 'date':
                case 'time':
                case 'timetz':
                    $r[] = 'date';
                    break;
                case 'json':
                    $r[] = 'array';
                    break;
                default:
                    $r[] = 'string';
                    if (!empty($meta['length'])) $r[] = "max:{$meta['length']}";
                    break;
            }

            $rules[$name] = array_values(array_unique($r));
        }
        return $rules;
    }

    /**
     * Tambahkan rule dari FK (exists) & unique indexes.
     * - Single unique: Rule::unique($table,$col) / ignore id saat update
     * - Composite unique: Rule::unique($table)->where(fn($q) => ...)
     */
    protected function applyRelationalRules(
        array $rules,
        string $table,
        bool $forUpdate,
        ?int $id,
        ?Model $modelInstance,
        Request $request
    ): array {
        $t = $this->doctrineTable($table);

        // FK → exists:foreign_table,foreign_column
        foreach ($t->getForeignKeys() as $fk) {
            $foreignTable = $fk->getForeignTableName();
            $foreignCols  = $fk->getForeignColumns(); // biasanya ['id']
            $localCols    = $fk->getLocalColumns();

            foreach ($localCols as $i => $local) {
                if (!isset($rules[$local])) continue;
                $foreignCol = $foreignCols[$i] ?? $foreignCols[0] ?? 'id';
                $rules[$local][] = "exists:{$foreignTable},{$foreignCol}";
            }
        }

        // Unique indexes
        foreach ($t->getIndexes() as $index) {
            if (! $index->isUnique()) continue;

            $cols = $index->getColumns();

            if (count($cols) === 1) {
                $col = $cols[0];
                // Jika field tidak tervalidasi (mis. di-ignore), lewati
                if (!isset($rules[$col])) continue;

                if ($forUpdate && $modelInstance) {
                    $rules[$col][] = Rule::unique($table, $col)
                        ->ignore($id, $modelInstance->getKeyName());
                } else {
                    $rules[$col][] = Rule::unique($table, $col);
                }
            } else {
                // Composite unique: kaitkan ke kolom pertama sebagai "anchor" rule
                $anchor = $cols[0];
                if (!isset($rules[$anchor])) $rules[$anchor] = [];
                $rule = Rule::unique($table)->where(function ($q) use ($cols, $request, $modelInstance, $forUpdate, $id) {
                    foreach ($cols as $c) {
                        $q->where($c, $request->input($c));
                    }
                    if ($forUpdate && $modelInstance) {
                        $q->where($modelInstance->getKeyName(), '!=', $id);
                    }
                });
                $rules[$anchor][] = $rule;
            }
        }

        return $rules;
    }

    protected function safeFill(array $data, array $columns, array $options = []): array
    {
        $ignore = array_merge(['id', 'created_at', 'updated_at', 'deleted_at'], $options['except'] ?? []);
        return collect($data)->only($options['only'] ?? $columns)->except($ignore)->toArray();
    }

    /* ===================== REST ===================== */

    public function index(Request $request)
    {
        $class = $this->modelClass($request);
        $table = $this->table($class);
        $cols  = $this->columns($table);

        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $class::query();

        // Eager load relasi: ?with=user,category,items.product
        if ($with = $request->query('with')) {
            $q->with(array_map('trim', explode(',', $with)));
        }

        // Filter sederhana ?field=value
        foreach ($request->query() as $k => $v) {
            if (in_array($k, $cols, true)) $q->where($k, $v);
        }

        // Sort ?sort=-created_at,name
        if ($sort = $request->query('sort')) {
            foreach (explode(',', $sort) as $s) {
                $dir = Str::startsWith($s, '-') ? 'desc' : 'asc';
                $col = ltrim($s, '-');
                if (in_array($col, $cols, true)) $q->orderBy($col, $dir);
            }
        }

        $per = min((int)$request->query('per_page', 15), 100);
        return $q->paginate($per);
    }

    public function store(Request $request)
    {
        $class = $this->modelClass($request);
        $opts  = $class::crudOptions();
        $table = $this->table($class);

        $rules = $this->baseRules($table, $opts, false);
        $rules = $this->applyRelationalRules($rules, $table, false, null, null, $request);

        $validated = $request->validate($rules);
        $data  = $this->safeFill($validated, $this->columns($table), $opts);

        /** @var Model $m */
        $m = $class::create($data);

        // Eager load jika diminta
        if ($with = $request->query('with')) {
            $m->load(array_map('trim', explode(',', $with)));
        }

        return response()->json($m, 201);
    }

    public function show(Request $request, $id)
    {
        $class = $this->modelClass($request);

        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $class::query();
        if ($with = $request->query('with')) {
            $q->with(array_map('trim', explode(',', $with)));
        }

        return $q->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $class = $this->modelClass($request);
        $opts  = $class::crudOptions();
        $table = $this->table($class);

        /** @var Model $m */
        $m = $class::findOrFail($id);

        $rules = $this->baseRules($table, $opts, true);
        $rules = $this->applyRelationalRules($rules, $table, true, (int)$id, $m, $request);

        $validated = $request->validate($rules);
        $data  = $this->safeFill($validated, $this->columns($table), $opts);

        $m->fill($data)->save();

        // Eager load jika diminta
        if ($with = $request->query('with')) {
            $m->load(array_map('trim', explode(',', $with)));
        }

        return $m;
    }

    public function destroy(Request $request, $id)
    {
        $class = $this->modelClass($request);
        $opts  = $class::crudOptions();

        /** @var Model $m */
        $m = $class::findOrFail($id);

        if (!empty($opts['softDeletes'])) $m->delete();
        else $m->forceDelete();

        return response()->noContent();
    }
}
