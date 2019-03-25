<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_SERVICE_NS;

	use Gobl\CRUD\Exceptions\CRUDException;
	use Gobl\DBAL\Relations\CallableVR;
	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Relations\VirtualRelation;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\DBAL\Types\TypeBigint;
	use Gobl\DBAL\Types\TypeInt;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
	use Gobl\ORM\Exceptions\ORMQueryException;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMServiceBase;
	use MY_PROJECT_DB_NS\MyController;
	use MY_PROJECT_DB_NS\MyEntity;
	use OZONE\OZ\Exceptions\BadRequestException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFieldException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Router\RouteContext;
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
	final class MyOZService extends ORMServiceBase
	{
		/**
		 * Maps form fields name to columns name
		 *
		 * @var array
		 */
		private static $columns_map = [
			//__OZONE_COLUMNS_NAME_MAP__
		];

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$table       = ORM::getDatabase('MY_PROJECT_DB_NS')
							  ->getTable(MyEntity::TABLE_NAME);
			$type_obj    = $table->getColumn('my_pk_column_const')
								 ->getTypeObject();
			$bigint_type = TypeBigint::class;
			$int_type    = TypeInt::class;
			$is_number   = ($type_obj instanceof $bigint_type OR $type_obj instanceof $int_type);

			$options = [
				'my_id'    => $is_number ? '[0-9]+' : '[^/]+',
				'relation' => Relation::NAME_PATTERN
			];

			$router->post('/my_svc', function (RouteContext $context) {
				$request_context = $context->getRequestContext();
				$service         = new MyOZService();
				$service->actionCreateEntity($request_context->getRequest()
															 ->getParams());

				return $service->writeResponse($request_context);
			}, $options)
				   ->get('/my_svc/{my_id}', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionGetEntity($request_context->getRequest()
																 ->getParams(), $context->getArgs());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->get('/my_svc', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionGetAll($request_context->getRequest()
															  ->getParams());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->get('/my_svc/{my_id}/{relation}', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionGetRelation($request_context->getRequest()
																   ->getParams(), $context->getArgs());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->patch('/my_svc/{my_id}', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionUpdateEntity($request_context->getRequest()
																	->getParams(), $context->getArgs());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->patch('/my_svc', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionUpdateAll($request_context->getRequest()
																 ->getParams());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->delete('/my_svc/{my_id}', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionDeleteEntity($context->getArgs());

					   return $service->writeResponse($request_context);
				   }, $options)
				   ->delete('/my_svc', function (RouteContext $context) {
					   $request_context = $context->getRequestContext();
					   $service         = new MyOZService();
					   $service->actionDeleteAll($request_context->getRequest()
																 ->getParams());

					   return $service->writeResponse($request_context);
				   }, $options);
		}

		/**
		 * Converts Gobl exceptions unto OZone exceptions.
		 *
		 * @param \Exception $error the exception to convert
		 *
		 * @throws \Exception
		 */
		private static function tryConvertException(\Exception $error)
		{
			if ($error instanceof ORMControllerFormException) {
				throw new InvalidFormException(null, [$error->getMessage(), $error->getData()], $error);
			}

			if ($error instanceof ORMQueryException) {
				throw new BadRequestException($error->getMessage(), $error->getData(), $error);
			}

			if ($error instanceof CRUDException) {
				throw new ForbiddenException($error->getMessage(), $error->getData(), $error);
			}

			if ($error instanceof TypesInvalidValueException) {
				// don't expose debug data to client, may contains sensitive data
				$debug         = $error->getDebugData();
				$data          = $error->getData();
				$data['field'] = $debug['field'];

				throw new InvalidFieldException($error->getMessage(), $data, $error);
			}

			throw $error;
		}

		//========================================================
		//=	POST REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		public function actionCreateEntity(array $request)
		{
			try {
				$form_values = self::onlyColumns(self::$columns_map, $request);

				$controller = new MyController();
				$entity     = $controller->addItem($form_values);

				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData(['item' => $entity]);
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		//========================================================
		//=	PATCH REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Exception
		 */
		public function actionUpdateEntity(array $request, array $extra)
		{
			try {
				$form_values = self::onlyColumns(self::$columns_map, $request);

				$controller = new MyController();
				$entity     = $controller->updateOneItem($extra, $form_values);

				if ($entity instanceof MyEntity) {
					$this->getResponseHolder()
						 ->setDone($controller->getCRUD()
											  ->getMessage())
						 ->setData(['item' => $entity]);
				} else {
					throw new NotFoundException();
				}
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		public function actionUpdateAll($request = [])
		{
			try {
				$data        = (isset($request['data']) AND is_array($request['data'])) ? $request['data'] : [];
				$form_values = self::onlyColumns(self::$columns_map, $data);

				if (empty($form_values)) {
					throw new InvalidFormException();
				}

				$filters = self::getRequestFilters(self::$columns_map, $request);

				$controller = new MyController();
				$count      = $controller->updateAllItems($filters, $data);

				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData(['affected' => $count]);
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		//========================================================
		//=	DELETE REQUEST METHODS
		//========================================================

		/**
		 * @param array $extra
		 *
		 * @throws \Exception
		 */
		public function actionDeleteEntity(array $extra)
		{
			try {
				$controller = new MyController();
				$entity     = $controller->deleteOneItem($extra);

				if ($entity instanceof MyEntity) {
					$this->getResponseHolder()
						 ->setDone($controller->getCRUD()
											  ->getMessage())
						 ->setData(['item' => $entity]);
				} else {
					throw new NotFoundException();
				}
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		public function actionDeleteAll($request = [])
		{
			try {
				$filters = self::onlyColumns(self::$columns_map, $request);

				$controller = new MyController();
				$count      = $controller->deleteAllItems($filters);

				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData(['affected' => $count]);
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		//========================================================
		//=	GET REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Exception
		 */
		public function actionGetEntity(array $request, array $extra)
		{
			try {
				$controller = new MyController();
				$entity     = $controller->getItem($extra);

				if (!$entity) {
					throw new NotFoundException();
				}

				$relations = $this->listEntityRelations($entity, $request);

				$this->getResponseHolder()
					 ->setDone($controller->getCRUD()
										  ->getMessage())
					 ->setData([
						 'item'      => $entity,
						 'relations' => $relations
					 ]);
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		public function actionGetAll(array $request)
		{
			try {
				$filters    = self::getRequestFilters(self::$columns_map, $request);
				$order_by   = self::getRequestOrderBy(self::$columns_map, $request);
				$collection = self::getRequestCollection($request);

				$p9            = self::getPagination($request);
				$max           = $p9['max'];
				$offset        = $p9['offset'];
				$page          = $p9['page'];
				$total_records = 0;

				$controller = new MyController();

				if ($collection) {
					$results = $controller->getCollectionItems($collection, $filters, $max, $offset, $order_by, $total_records);
				} else {
					$results = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
				}

				$relations = [];

				if (count($results)) {
					$relations = $this->listEntitiesRelations($results, $request);
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
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Exception
		 */
		public function actionGetRelation(array $request, array $extra)
		{
			try {
				if (!isset($extra['my_pk_column_const'])) {
					throw new NotFoundException();
				}

				$filters['my_pk_column_const'] = $extra['my_pk_column_const'];
				$req_rel_name                  = $extra['relation'];
				$request['relations']          = $req_rel_name;

				$controller = new MyController();
				$entity     = $controller->getItem($filters);

				if (!$entity) {
					throw new NotFoundException();
				}

				$p9            = self::getPagination($request);
				$max           = $p9['max'];
				$offset        = $p9['offset'];
				$page          = $p9['page'];
				$total_records = 0;

				$relations = $this->listEntityRelations($entity, $request, $max, $offset, $total_records);
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
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * @param \MY_PROJECT_DB_NS\MyEntity $entity
		 * @param array                      $request
		 * @param null                       $max
		 * @param int                        $offset
		 * @param int                        $total_records
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function listEntityRelations(MyEntity $entity, array $request, $max = null, $offset = 0, &$total_records = null)
		{
			$request_relations = self::getRequestRelations($request);
			$relations         = [];

			if (!empty($request_relations)) {
				$rel_map = $this->resolveRelations($request_relations);

				foreach ($rel_map as $name => $rel) {
					if ($rel instanceof Relation) {
						/**@var Relation $rel */
						$rel_type = $rel->getType();
						if ($rel_type === Relation::ONE_TO_MANY OR $rel_type === Relation::MANY_TO_MANY) {
							$relations[$name] = $this->getRelationItemsList($rel, $entity, $request, $max, $offset, $total_records);
						} else {
							$relations[$name] = $this->getRelationItem($rel, $entity);
						}
					} elseif ($rel instanceof VirtualRelation) {
						/**@var VirtualRelation $rel */
						$relations[$name] = $rel->run($entity, $request, $max, $offset, $total_records);
					}
				}
			}

			return $relations;
		}

		/**
		 * @param \MY_PROJECT_DB_NS\MyEntity[] $entities
		 * @param array                        $request
		 * @param null                         $max
		 * @param int                          $offset
		 * @param int                          $total_records
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function listEntitiesRelations(array $entities, array $request, $max = null, $offset = 0, &$total_records = null)
		{
			$request_relations = self::getRequestRelations($request);
			$relations         = [];

			if (!empty($request_relations)) {
				$rel_map = $this->resolveRelations($request_relations);

				foreach ($rel_map as $name => $rel) {
					if ($rel instanceof Relation) {
						foreach ($entities as $entity) {
							$arr      = $entity->asArray(false);
							$id       = $arr['my_pk_column_const'];
							$rel_type = $rel->getType();
							if ($rel_type === Relation::ONE_TO_MANY OR $rel_type === Relation::MANY_TO_MANY) {
								$relations[$name][$id] = $this->getRelationItemsList($rel, $entity, $request, $max, $offset, $total_records);
							} else {
								$relations[$name][$id] = $this->getRelationItem($rel, $entity);
							}
						}
					} elseif ($rel instanceof VirtualRelation) {
						if ($rel instanceof CallableVR AND $rel->canHandleList()) {
							$relations[$name] = $rel->run($entities, $request, $max, $offset, $total_records);
						} else {
							foreach ($entities as $entity) {
								$arr                   = $entity->asArray(false);
								$id                    = $arr['my_pk_column_const'];
								$relations[$name][$id] = $rel->run($entity, $request, $max, $offset, $total_records);
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
		 * @param array                         $request
		 *
		 * @param int                           $max
		 * @param int                           $offset
		 * @param int                           $total_records
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private function getRelationItemsList(Relation $relation, MyEntity $entity, array $request, $max = null, $offset = 0, &$total_records = null)
		{
			$target_columns_map = [];
			$target_columns     = $relation->getTargetTable()
										   ->getColumns();

			foreach ($target_columns as $column) {
				$target_columns_map[$column->getFullName()] = 1;
			}

			$filters  = self::getRequestFilters($target_columns_map, $request);
			$order_by = self::getRequestOrderBy($target_columns_map, $request);

			$relation_getter = $relation->getGetterName();
			$items           = call_user_func_array([
				$entity,
				$relation_getter
			], [$filters, $max, $offset, $order_by, &$total_records]);

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
		 * @param array $request_relations
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function resolveRelations(array $request_relations)
		{
			$table   = ORM::getDatabase('MY_PROJECT_DB_NS')
						  ->getTable(MyEntity::TABLE_NAME);
			$missing = [];
			$rel_map = [];

			// we firstly check all relation
			foreach ($request_relations as $name) {
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