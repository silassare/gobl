<?php

//__GOBL_HEAD_COMMENT__

namespace MY_PROJECT_SERVICE_NS;

use Exception;
use Gobl\DBAL\Relations\CallableVR;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\ORM;
use MY_PROJECT_DB_NS\MyController;
use MY_PROJECT_DB_NS\MyEntity;
use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\ORMRequest;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

defined('OZ_SELF_SECURITY_CHECK') or die;

/**
 * Class MyOZService
 *
 * to add item to my_svc
 * - POST    /my_svc
 *
 * to update property(ies) of the item with the given :my_id
 * - PATCH     /my_svc/:my_id
 *
 * to update property(ies) of all items in `my_table`
 * - PATCH     /my_svc
 *
 * to delete item with the given :my_id
 * - DELETE  /my_svc/:my_id
 *
 * to delete all items in `my_table`
 * - DELETE  /my_svc
 *
 * to get the item with the given :my_id
 * - GET     /my_svc/:my_id
 *
 * to get all items in my_table
 * - GET     /my_svc
 *
 * to get relation for the item in `my_table` with the given :my_id
 * - GET     /my_svc/:my_id/relation
 *
 * @package MY_PROJECT_DB_NS\App\Services
 */
final class MyOZService extends BaseService
{
	/**
	 * @return \Gobl\DBAL\Table
	 */
	public static function table()
	{
		return ORM::getDatabase('MY_PROJECT_DB_NS')
				  ->getTable(MyEntity::TABLE_NAME);
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$type_obj    = self::table()
						   ->getColumn('my_pk_column_const')
						   ->getTypeObject();
		$bigint_type = TypeBigint::class;
		$int_type    = TypeInt::class;
		$is_number   = ($type_obj instanceof $bigint_type or $type_obj instanceof $int_type);

		$options = [
			'my_id'    => $is_number ? '[0-9]+' : '[^/]+',
			'relation' => Relation::NAME_PATTERN
		];

		$router->post('/my_svc', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
															->getFormData());

			$service = new self($context);
			$service->actionCreateEntity($orm_request);

			return $service->respond();
		}, $options)
			   ->get('/my_svc/{my_id}', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $orm_request->addColumnFilter('my_id', $r->getArg('my_id'));
				   $service = new self($context);
				   $service->actionGetEntity($orm_request);

				   return $service->respond();
			   }, $options)
			   ->get('/my_svc', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $service     = new self($context);
				   $service->actionGetAll($orm_request);

				   return $service->respond();
			   }, $options)
			   ->get('/my_svc/{my_id}/{relation}', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $orm_request->addColumnFilter('my_id', $r->getArg('my_id'));
				   $orm_request->addRelation($r->getArg('relation'));

				   $service = new self($context);
				   $service->actionGetRelation($orm_request);

				   return $service->respond();
			   }, $options)
			   ->patch('/my_svc/{my_id}', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $orm_request->addColumnFilter('my_id', $r->getArg('my_id'));

				   $service = new self($context);
				   $service->actionUpdateOneItem($orm_request);

				   return $service->respond();
			   }, $options)
			   ->patch('/my_svc', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $service     = new self($context);
				   $service->actionUpdateAllItems($orm_request);

				   return $service->respond();
			   }, $options)
			   ->delete('/my_svc/{my_id}', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $orm_request->addColumnFilter('my_id', $r->getArg('my_id'));

				   $service = new self($context);
				   $service->actionDeleteEntity($orm_request);

				   return $service->respond();
			   }, $options)
			   ->delete('/my_svc', function (RouteInfo $r) {
				   $context     = $r->getContext();
				   $orm_request = new ORMRequest($context, $context->getRequest()
																   ->getFormData());
				   $orm_request->addColumnFilter('my_id', $r->getArg('my_id'));

				   $service = new self($context);
				   $service->actionDeleteAll($orm_request);

				   return $service->respond();
			   }, $options);
	}

	//========================================================
	//=	POST REQUEST METHODS
	//========================================================

	/**
	 * Creates a new entry in the table `my_table`
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionCreateEntity(ORMRequest $orm_request)
	{
		try {
			$values = $orm_request->getFormData(self::table());

			$controller = new MyController();
			$entity     = $controller->addItem($values);

			$this->getResponseHolder()
				 ->setDone($controller->getCRUD()
									  ->getMessage())
				 ->setData(['item' => $entity]);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	//========================================================
	//=	PATCH REQUEST METHODS
	//========================================================

	/**
	 * Updates only one item in the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionUpdateOneItem(ORMRequest $orm_request)
	{
		try {
			$orm_request = $orm_request->createScopedInstance(self::table());
			$values      = $orm_request->getFormData();
			$filters     = $orm_request->getFilters();

			$controller = new MyController();
			$entity     = $controller->updateOneItem($filters, $values);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData(['item' => $entity]);
			} else {
				throw new NotFoundException();
			}
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	/**
	 * Updates all items in the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionUpdateAllItems(ORMRequest $orm_request)
	{
		try {
			$orm_request = $orm_request->createScopedInstance(self::table());
			$values      = $orm_request->getFormData();
			$filters     = $orm_request->getFilters();

			$controller = new MyController();
			$count      = $controller->updateAllItems($filters, $values);

			$this->getResponseHolder()
				 ->setDone($controller->getCRUD()
									  ->getMessage())
				 ->setData(['affected' => $count]);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	//========================================================
	//=	DELETE REQUEST METHODS
	//========================================================

	/**
	 * Deletes only one item in the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionDeleteEntity(ORMRequest $orm_request)
	{
		try {
			$filters = $orm_request->getFilters(self::table());

			$controller = new MyController();
			$entity     = $controller->deleteOneItem($filters);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData(['item' => $entity]);
			} else {
				throw new NotFoundException();
			}
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	/**
	 * Deletes all items in the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionDeleteAll(ORMRequest $orm_request)
	{
		try {
			$filters = $orm_request->getFilters(self::table());

			$controller = new MyController();
			$count      = $controller->deleteAllItems($filters);

			$this->getResponseHolder()
				 ->setDone($controller->getCRUD()
									  ->getMessage())
				 ->setData(['affected' => $count]);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	//========================================================
	//=	GET REQUEST METHODS
	//========================================================

	/***
	 * Gets only one item from the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionGetEntity(ORMRequest $orm_request)
	{
		try {
			$orm_request = $orm_request->createScopedInstance(self::table());
			$filters     = $orm_request->getFilters();
			$order_by    = $orm_request->getOrderBy();

			$controller = new MyController();
			$entity     = $controller->getItem($filters, $order_by);

			if (!$entity) {
				throw new NotFoundException();
			}

			$relations = $this->listEntityRelations($entity, $orm_request);

			$this->getResponseHolder()
				 ->setDone($controller->getCRUD()
									  ->getMessage())
				 ->setData([
					 'item'      => $entity,
					 'relations' => $relations
				 ]);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	/**
	 * Gets all items from the table `my_table` that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionGetAll(ORMRequest $orm_request)
	{
		try {
			$collection = $orm_request->getCollection();

			$orm_request   = $orm_request->createScopedInstance(self::table());
			$filters       = $orm_request->getFilters();
			$order_by      = $orm_request->getOrderBy();
			$max           = $orm_request->getMax();
			$offset        = $orm_request->getOffset();
			$page          = $orm_request->getPage();
			$total_records = 0;

			$controller = new MyController();

			if ($collection) {
				$table      = ORM::getDatabase('MY_PROJECT_DB_NS')
								 ->getTable(MyEntity::TABLE_NAME);
				$collection = $table->getCollection($orm_request->getCollection());

				if (!$collection) {
					throw new NotFoundException();
				}

				$results = $collection->run($orm_request, $total_records);
			} else {
				$results = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
			}

			$relations = [];

			if (count($results)) {
				$relations = $this->listEntitiesRelations($results, $orm_request);
			}

			$this->getResponseHolder()
				 ->setDone($controller->getCRUD()
									  ->getMessage())
				 ->setData([
					 'items'     => $results,
					 'max'       => $max,
					 'page'      => $page,
					 'total'     => $total_records,
					 'relations' => $relations
				 ]);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	/**
	 * Gets relation item(s) that matches some filters
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws \Exception
	 */
	public function actionGetRelation(ORMRequest $orm_request)
	{
		try {
			if (!$orm_request->getColumnFilters('my_pk_column_const')) {
				throw new NotFoundException();
			}

			$filters      = $orm_request->getFilters(self::table());
			$req_rel_name = $orm_request->getRelations()[0];

			$controller = new MyController();
			$entity     = $controller->getItem($filters);

			if (!$entity) {
				throw new NotFoundException();
			}

			$max           = $orm_request->getMax();
			$page          = $orm_request->getPage();
			$total_records = 0;

			$relations = $this->listEntityRelations($entity, $orm_request, $total_records);
			$r         = $relations[$req_rel_name];

			if (is_array($r)) {
				$data = [
					'items' => $r,
					'max'   => $max,
					'page'  => $page,
					'total' => $total_records
				];
			} else {
				$data = [
					'item' => $r
				];
			}

			$this->getResponseHolder()
				 ->setDone()
				 ->setData($data);
		} catch (Exception $e) {
			self::tryConvertException($e);
		}
	}

	/**
	 * @param \MY_PROJECT_DB_NS\MyEntity $entity
	 * @param \OZONE\OZ\Core\ORMRequest  $orm_request
	 * @param int                        $total_records
	 *
	 * @return array
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	private function listEntityRelations(MyEntity $entity, ORMRequest $orm_request, &$total_records = null)
	{
		$query_relations = $orm_request->getRelations();
		$relations       = [];

		if (!empty($query_relations)) {
			$rel_map = $this->resolveRelations($query_relations);

			foreach ($rel_map as $name => $rel) {
				if ($rel instanceof Relation) {
					/**@var Relation $rel */
					$rel_type = $rel->getType();
					if ($rel_type === Relation::ONE_TO_MANY or $rel_type === Relation::MANY_TO_MANY) {
						$relations[$name] = $this->getRelationItemsList($rel, $entity, $orm_request, $total_records);
					} else {
						$relations[$name] = $this->getRelationItem($rel, $entity);
					}
				} elseif ($rel instanceof VirtualRelation) {
					/**@var VirtualRelation $rel */
					$relations[$name] = $rel->run($entity, $orm_request, $total_records);
				}
			}
		}

		return $relations;
	}

	/**
	 * @param \MY_PROJECT_DB_NS\MyEntity[] $entities
	 * @param \OZONE\OZ\Core\ORMRequest    $orm_request
	 * @param int                          $total_records
	 *
	 * @return array
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	private function listEntitiesRelations(array $entities, ORMRequest $orm_request, &$total_records = null)
	{
		$query_relations = $orm_request->getRelations();
		$relations       = [];

		if (!empty($query_relations)) {
			$rel_map = $this->resolveRelations($query_relations);

			foreach ($rel_map as $name => $rel) {
				if ($rel instanceof Relation) {
					foreach ($entities as $entity) {
						$arr      = $entity->asArray(false);
						$id       = $arr['my_pk_column_const'];
						$rel_type = $rel->getType();
						if ($rel_type === Relation::ONE_TO_MANY or $rel_type === Relation::MANY_TO_MANY) {
							$relations[$name][$id] = $this->getRelationItemsList(
								$rel,
								$entity,
								$orm_request,
								$total_records
							);
						} else {
							$relations[$name][$id] = $this->getRelationItem($rel, $entity);
						}
					}
				} elseif ($rel instanceof VirtualRelation) {
					if ($rel instanceof CallableVR and $rel->canHandleList()) {
						$relations[$name] = $rel->run($entities, $orm_request, $total_records);
					} else {
						foreach ($entities as $entity) {
							$arr                   = $entity->asArray(false);
							$id                    = $arr['my_pk_column_const'];
							$relations[$name][$id] = $rel->run($entity, $orm_request, $total_records);
						}
					}
				}
			}
		}

		return $relations;
	}

	/**
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \MY_PROJECT_DB_NS\MyEntity    $entity
	 * @param \OZONE\OZ\Core\ORMRequest     $orm_request
	 * @param int                           $total_records
	 *
	 * @return array
	 */
	private function getRelationItemsList(
		Relation $relation,
		MyEntity $entity,
		ORMRequest $orm_request,
		&$total_records = null
	) {
		$target_columns_map = [];
		$target_columns     = $relation->getTargetTable()
									   ->getColumns();

		foreach ($target_columns as $column) {
			$target_columns_map[$column->getFullName()] = 1;
		}

		$relation_getter = $relation->getGetterName();
		$items           = call_user_func_array([
			$entity,
			$relation_getter
		], [
			$orm_request->getFilters(),
			$orm_request->getMax(),
			$orm_request->getOffset(),
			$orm_request->getOrderBy(),
			&$total_records
		]);

		return $items;
	}

	/**
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \MY_PROJECT_DB_NS\MyEntity    $entity
	 *
	 * @return mixed
	 */
	private function getRelationItem(Relation $relation, MyEntity $entity)
	{
		$filters          = [];
		$relation_columns = $relation->getRelationColumns();
		$entity_data      = $entity->asArray();

		foreach ($relation_columns as $from => $target) {
			$filters[$target] = $entity_data[$from];
		}

		$relation_getter = $relation->getGetterName();
		$item            = call_user_func([$entity, $relation_getter]);

		return $item;
	}

	/**
	 * @param array $relations
	 *
	 * @return array
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	private function resolveRelations(array $relations)
	{
		$table   = ORM::getDatabase('MY_PROJECT_DB_NS')
					  ->getTable(MyEntity::TABLE_NAME);
		$missing = [];
		$rel_map = [];

		// we firstly check all relation
		foreach ($relations as $name) {
			if ($table->hasRelation($name)) {
				$rel_map[$name] = $table->getRelation($name);
			} elseif ($table->hasVirtualRelation($name)) {
				$rel_map[$name] = $table->getVirtualRelation($name);
			} else {
				$missing[] = $name;
			}
		}

		// checks if there are missing relations
		if (count($missing)) {
			throw new NotFoundException(null, ['RELATIONS_MISSING', $missing]);
		}

		return $rel_map;
	}
}
