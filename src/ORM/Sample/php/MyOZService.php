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
	use Gobl\ORM\ORMForm;
	use Gobl\ORM\ORMServiceBase;
	use MY_PROJECT_DB_NS\MyController;
	use MY_PROJECT_DB_NS\MyEntity;
	use OZONE\OZ\Exceptions\BadRequestException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFieldException;
	use OZONE\OZ\Exceptions\InvalidFormException;
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

			$router->post('/my_svc', function (RouteInfo $r) {
				$context = $r->getContext();
				$service = new self($context);
				$service->actionCreateEntity($context->getRequest()
													 ->getFormData());

				return $service->writeResponse($context);
			}, $options)
				   ->get('/my_svc/{my_id}', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionGetEntity($context->getRequest()
														 ->getFormData(), $r->getArgs());

					   return $service->writeResponse($context);
				   }, $options)
				   ->get('/my_svc', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionGetAll($context->getRequest()
													  ->getFormData());

					   return $service->writeResponse($context);
				   }, $options)
				   ->get('/my_svc/{my_id}/{relation}', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionGetRelation($context->getRequest()
														   ->getFormData(), $r->getArgs());

					   return $service->writeResponse($context);
				   }, $options)
				   ->patch('/my_svc/{my_id}', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionUpdateOneItem($context->getRequest()
															 ->getFormData(), $r->getArgs());

					   return $service->writeResponse($context);
				   }, $options)
				   ->patch('/my_svc', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionUpdateAll($context->getRequest()
														 ->getFormData());

					   return $service->writeResponse($context);
				   }, $options)
				   ->delete('/my_svc/{my_id}', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionDeleteEntity($r->getArgs());

					   return $service->writeResponse($context);
				   }, $options)
				   ->delete('/my_svc', function (RouteInfo $r) {
					   $context = $r->getContext();
					   $service = new self($context);
					   $service->actionDeleteAll($context->getRequest()
														 ->getFormData());

					   return $service->writeResponse($context);
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
				throw new InvalidFormException(null, [
					'message' => $error->getMessage(),
					'data'    => $error->getData()
				], $error);
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
		 * Creates a new entry in the table `my_table`
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionCreateEntity(ORMForm $form)
		{
			try {
				$values = $form->getFormData(self::$columns_map);

				$controller = new MyController();
				$entity     = $controller->addItem($values);

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
		 * Updates only one item in the table `my_table` that matches the filters in the form
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionUpdateOneItem(ORMForm $form)
		{
			try {
				$values  = $form->getFormData(self::$columns_map);
				$filters = $form->getFilters(self::$columns_map);

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
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * Updates all items in the table `my_table` that matches the filters in the form
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionUpdateAllItems(ORMForm $form)
		{
			try {
				$values  = $form->getFormData(self::$columns_map);
				$filters = $form->getFilters(self::$columns_map);

				$controller = new MyController();
				$count      = $controller->updateAllItems($filters, $values);

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
		 * Deletes only one item in the table `my_table` that matches the filters in the form
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionDeleteEntity(ORMForm $form)
		{
			try {
				$filters = $form->getFilters(self::$columns_map);

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
			} catch (\Exception $e) {
				self::tryConvertException($e);
			}
		}

		/**
		 * Deletes all items in the table `my_table` that matches the filters in the form
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionDeleteAll(ORMForm $form)
		{
			try {
				$filters = $form->getFilters(self::$columns_map);

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

		/***
		 * Gets only one item from the table `my_table` that matches the filters in the form
		 *
		 * @param \Gobl\ORM\ORMForm $form
		 *
		 * @throws \Exception
		 */
		public function actionGetEntity(ORMForm $form)
		{
			try {
				$filters  = $form->getFilters(self::$columns_map);
				$order_by = $form->getOrderBy(self::$columns_map);

				$controller = new MyController();
				$entity     = $controller->getItem($filters, $order_by);

				if (!$entity) {
					throw new NotFoundException();
				}

				$relations = $this->listEntityRelations($entity, $form);

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
		 * @param array $form
		 *
		 * @throws \Exception
		 */
		public function actionGetAll(array $form)
		{
			try {
				$filters    = self::getRequestFilters(self::$columns_map, $form);
				$order_by   = self::getRequestOrderBy(self::$columns_map, $form);
				$collection = self::getRequestCollection($form);

				$p9            = self::getPagination($form);
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
					$relations = $this->listEntitiesRelations($results, $form);
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
		 * @param array $form
		 * @param array $extra
		 *
		 * @throws \Exception
		 */
		public function actionGetRelation(array $form, array $extra)
		{
			try {
				if (!isset($extra['my_pk_column_const'])) {
					throw new NotFoundException();
				}

				$filters['my_pk_column_const'] = $extra['my_pk_column_const'];
				$req_rel_name                  = $extra['relation'];
				$form['relations']             = $req_rel_name;

				$controller = new MyController();
				$entity     = $controller->getItem($filters);

				if (!$entity) {
					throw new NotFoundException();
				}

				$p9            = self::getPagination($form);
				$max           = $p9['max'];
				$offset        = $p9['offset'];
				$page          = $p9['page'];
				$total_records = 0;

				$relations = $this->listEntityRelations($entity, $form, $max, $offset, $total_records);
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
		 * @param array                      $form
		 * @param null                       $max
		 * @param int                        $offset
		 * @param int                        $total_records
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function listEntityRelations(MyEntity $entity, ORMForm $form, $max = null, $offset = 0, &$total_records = null)
		{
			$form_relations = $form->getRelations();
			$relations      = [];

			if (!empty($form_relations)) {
				$rel_map = $this->resolveRelations($form_relations);

				foreach ($rel_map as $name => $rel) {
					if ($rel instanceof Relation) {
						/**@var Relation $rel */
						$rel_type = $rel->getType();
						if ($rel_type === Relation::ONE_TO_MANY OR $rel_type === Relation::MANY_TO_MANY) {
							$relations[$name] = $this->getRelationItemsList($rel, $entity, $form, $max, $offset, $total_records);
						} else {
							$relations[$name] = $this->getRelationItem($rel, $entity);
						}
					} elseif ($rel instanceof VirtualRelation) {
						/**@var VirtualRelation $rel */
						$relations[$name] = $rel->run($entity, $form, $max, $offset, $total_records);
					}
				}
			}

			return $relations;
		}

		/**
		 * @param \MY_PROJECT_DB_NS\MyEntity[] $entities
		 * @param array                        $form
		 * @param null                         $max
		 * @param int                          $offset
		 * @param int                          $total_records
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function listEntitiesRelations(array $entities, array $form, $max = null, $offset = 0, &$total_records = null)
		{
			$form_relations = self::getRequestRelations($form);
			$relations      = [];

			if (!empty($form_relations)) {
				$rel_map = $this->resolveRelations($form_relations);

				foreach ($rel_map as $name => $rel) {
					if ($rel instanceof Relation) {
						foreach ($entities as $entity) {
							$arr      = $entity->asArray(false);
							$id       = $arr['my_pk_column_const'];
							$rel_type = $rel->getType();
							if ($rel_type === Relation::ONE_TO_MANY OR $rel_type === Relation::MANY_TO_MANY) {
								$relations[$name][$id] = $this->getRelationItemsList($rel, $entity, $form, $max, $offset, $total_records);
							} else {
								$relations[$name][$id] = $this->getRelationItem($rel, $entity);
							}
						}
					} elseif ($rel instanceof VirtualRelation) {
						if ($rel instanceof CallableVR AND $rel->canHandleList()) {
							$relations[$name] = $rel->run($entities, $form, $max, $offset, $total_records);
						} else {
							foreach ($entities as $entity) {
								$arr                   = $entity->asArray(false);
								$id                    = $arr['my_pk_column_const'];
								$relations[$name][$id] = $rel->run($entity, $form, $max, $offset, $total_records);
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
		 * @param array                         $form
		 * @param int                           $max
		 * @param int                           $offset
		 * @param int                           $total_records
		 *
		 * @return array
		 */
		private function getRelationItemsList(Relation $relation, MyEntity $entity, ORMForm $form, $max = null, $offset = 0, &$total_records = null)
		{
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
			], [$form, &$total_records]);

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
		 * @param array $form_relations
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function resolveRelations(array $form_relations)
		{
			$table   = ORM::getDatabase('MY_PROJECT_DB_NS')
						  ->getTable(MyEntity::TABLE_NAME);
			$missing = [];
			$rel_map = [];

			// we firstly check all relation
			foreach ($form_relations as $name) {
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