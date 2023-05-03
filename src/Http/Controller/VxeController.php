<?php

namespace VxeController\Http\Controller;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

abstract class VxeController extends Controller implements VxeControllerInterface {
  const FILTER_DATA_KEY = 'datas';
  const FILTER_VALUE_KEY = 'values';

  /**
   * @var bool Enable or disable route for update action
   */
  public bool $routeUpdate = true;

  /**
   * @var bool Enable or disable router for destroy action
   */
  public bool $routeDestroy = true;

  /**
   * Relations to automatically eager load
   *
   * @var array
   */
  protected $eagerLoad = [];

  /**
   * Relations to automatically eager update
   *
   * @var array
   */
  protected $eagerSave = [];

  protected function filters(): array {
    return [];
  }

  protected function query(): Builder {
    return $this->model()::query();
  }

  protected function beforeQuery(Request $request, Builder $query) {
  }

  protected function beforeUpdate(array &$body) {
  }

  protected function afterUpdate($record, array $body) {
  }

  /**
   * Display a listing of the resource.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function index(Request $request): JsonResponse {
    if ($request->has('id')) {
      return $this->getOne($request);
    }

    if ($request->has('page')) {
      return $this->paginate($request);
    }

    $query = $this->getDataQuery($request);
    $items = $query->get();

    return new JsonResponse([
      'status' => true,
      'data' => $items
    ]);
  }

  /**
   * Returns one record by id
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getOne(Request $request): JsonResponse {
    $id = $request->get('id', 0);

    $query = $this->getDataQuery($request);

    /** @var ?Model $item */
    $item = $query->find($id);

    if (!$item) {
      return new JsonResponse([
        'status' => false,
        'message' => 'Not found'
      ]);
    }

    return new JsonResponse([
      'status' => true,
      'data' => $item
    ]);
  }

  /**
   * Returns paginated items
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function paginate(Request  $request): JsonResponse {
    $page = $request->get('page', 0);
    $limit = $request->get('limit', 50);

    $query = $this->getDataQuery($request);

    $paginator = $query->paginate($limit, ['*'], 'page', $page);

    return new JsonResponse([
      'status' => true,
      'data' => $paginator->items(),
      'total' => $paginator->total()
    ]);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function update(Request $request): JsonResponse {
    $body = $request->get('body', []);

    $model = App::make($this->model());
    $key = $model->getKeyName();
    $id = Arr::get($body, 'id');

    $this->beforeUpdate($body);

    $record = $this->query()->updateOrCreate(
      [$key => $id],
      $body
    );

    $this->eagerUpdate($record, $body);
    $this->afterUpdate($record, $body);

    $record->load($this->eagerLoad);

    return new JsonResponse([
      'status' => true,
      'data' => $record
    ]);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function destroy(Request $request): JsonResponse {
    $id = $request->get('id');

    $status = false;
    $message = null;

    try {
      if ($id) {
        $ids = is_array($id) ? $id : [$id];
        $deletedCount = $this->query()->whereIn('id', $ids)->delete();

        if ($deletedCount > 0) {
          $status = true;
        } else {
          $message = Lang::get('error.reference.404');
        }
      }
    } catch (QueryException $e) {
      $code = $e->getCode();
      $translationKey = "SQL error $code";

      $message = Lang::hasForLocale($translationKey)
        ? Lang::get($translationKey)
        : Lang::get('Unhandled SQL error', ['code' => $code]);
    } catch (\Exception $e) {
      $message = $e->getMessage();
    }

    return new JsonResponse([
      'status' => $status,
      'message' => $message
    ]);
  }

  private function eagerUpdate($record, $body) {
    foreach ($this->eagerSave as $attribute) {
      $values = Arr::get($body, $attribute);

      if (is_array($values)) {
        $record->$attribute()->sync($values);
      }
    }
  }

  protected function getDataQuery(Request $request): Builder {
    $order = $request->get('order', 'asc');
    $sort = $request->get('sort');

    $query = $this->query();

    $table = $query->getModel()->getTable();

    $query
      ->select($table.'.*')
      ->with($this->eagerLoad);

    $this->applyQueryParams($request, $query);

    $this->beforeQuery($request, $query);

    if (isset($sort) && isset($order)) {
      $query->reorder($table.'.'.$sort, $order);
    }

    return $query;
  }

  protected function applyQueryParams(Request $request, Builder $query) {
    $relations = $request->get('relations');
    $filters = $request->get('filter');

    if ($relations) {
      $query->with($relations);
    }

    if ($filters) {
      $this->applyFilters($query, $filters);
    }
  }

  protected function applyFilters($query, $filters) {
    $modelFilters = $this->filters();

    foreach ($filters as $field => $value) {
      $filterDefinition = array_key_exists($field, $modelFilters) ? $modelFilters[$field] : [];

      if (array_key_exists('query', $filterDefinition)) {
        $fieldValue = Arr::get($filters, $field);
        $filterDefinition['query']->call($this, $fieldValue, $filters, $query);
      } else if (is_array($value) || Arr::get($filterDefinition, 'type') == 'FilterValue') {
        $this->applyTypedFilter($query, $field, $value);
      } else {
        $query->where($query->getModel()->getTable().'.'.$field, '=', $value);
      }
    }
  }

  protected function applyTypedFilter(Builder $query, string $field, array $filter, string $boolean = 'and') {
    $method = $boolean === 'or' ? 'orWhere' : 'where';

    $query->$method(function (Builder $query) use ($field, $filter) {
      $fullField = $query->getModel()->getTable().'.'.$field;

      $datas = Arr::get($filter, self::FILTER_DATA_KEY);
      $values = Arr::get($filter, self::FILTER_VALUE_KEY);

      if (is_array($datas)) {
        foreach ($datas as $value) {
          if (is_null($value)) {
            continue;
          }

          if (is_array($value)) {
            $query->orWhereBetween($fullField, $value);
          } else {
            if (is_string($value)) {
              $query->orWhere($fullField, 'LIKE', '%' . $value . '%');
            } else {
              $query->orWhere($fullField, '=', $value);
            }
          }
        }
      }

      if (is_array($values)) {
        foreach ($values as $value) {
          if (is_null($value)) {
            continue;
          }

          $query->orWhere($fullField, '=', $value);
        }
      }
    });
  }
}
