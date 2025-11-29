<?php

namespace App;

trait QueryFilterTrait
{
    /**
     * Apply a single filter rule
     */
    public function applyFilter($query, $column, $operator, $value): void
    {
        switch ($operator) {
            case 'like':
                $query->where($column, 'like', "%{$value}%");
                break;
            case 'not_like':
                $query->where($column, 'not like', "%{$value}%");
                break;
            case '=':
            case '!=':
            case '<':
            case '<=':
            case '>':
            case '>=':
                $query->where($column, $operator, $value);
                break;
            case 'in':
                $query->whereIn($column, (array) $value);
                break;
            case 'not_in':
                $query->whereNotIn($column, (array) $value);
                break;
            case 'is_null':
                $query->whereNull($column);
                break;
            case 'is_not_null':
                $query->whereNotNull($column);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($column, $value);
                }
                break;
        }
    }

    /**
     * Apply JSON-based filters (filters from "filters: []" array)
     */
    public function applyJsonFilters($query, $request): void
    {
        $filters = json_decode($request->input('filters'), true);

        if (!$filters) {
            return;
        }

        foreach ($filters as $filter) {
            if (!isset($filter['id'], $filter['operator'], $filter['value'])) {
                continue;
            }

            $this->applyFilter(
                $query,
                $filter['id'],
                $filter['operator'],
                $filter['value']
            );
        }
    }

    /**
     * Apply URL query params like ?name=abc&price=100
     */
    public function applyUrlFilters($query, $request, array $allowedColumns): void
    {
        foreach ($allowedColumns as $column) {
            if ($request->has($column)) {
                $value = $request->input($column);

                // Automatic operator detection
                $operator = is_string($value) ? 'like' : '=';

                if (is_string($value) && preg_match('/^\d+$/', $value)) {
                    $operator = '='; // numbers should be = by default
                }

                $this->applyFilter($query, $column, $operator, $value);
            }
        }
    }

    /**
     * Apply sorting from URL query params like ?sort=[{"id":"name","desc":true}]
     */
    public function applySorting($query, $request): void
    {
        $sortParam = $request->input('sort');
        
        if (!$sortParam) {
            return;
        }
        
        $sortRules = json_decode($sortParam, true);
        
        if (!is_array($sortRules)) {
            return;
        }
        
        foreach ($sortRules as $rule) {
            if (!isset($rule['id'])) {
                continue;
            }
            
            $direction = isset($rule['desc']) && $rule['desc'] ? 'desc' : 'asc';
            $query->orderBy($rule['id'], $direction);
        }
    }
}
