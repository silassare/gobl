<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_SERVICE_NS;

	use Gobl\CRUD\Exceptions\CRUDException;
	use Gobl\DBAL\Relations\CallableVR;
	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Relations\VirtualRelation;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
	use Gobl\ORM\Exceptions\ORMQueryException;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMServiceBase;
	use MY_PROJECT_DB_NS\MyController;
	use MY_PROJECT_DB_NS\MyEntity;
	use OZONE\OZ\Core\RequestHandler;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\BadRequestException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFieldException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\MethodNotAllowedException;
	use OZONE\OZ\Exceptions\NotFoundException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class MyOZService
	 *
	 * to add item to my_svc
	 * - POST    /my_svc
	 *
	 * to update property(ies) of the item with the given :my_id
	 * - PUT     /my_svc/:my_id
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
		// for url like /my_svc/:my_id
		private static $identified_extra_reg = '#^([a-zA-Z0-9]+)/?$#';

		// for url like /my_svc/:my_id/relation
		private static $identified_relation_extra_reg = '#^([a-zA-Z0-9]+)/([a-zA-Z0-9_-]+)/?$#';

		/**
		 * maps form fields name to columns name
		 *
		 * @var array
		 */
		private static $columns_map = [
			//__OZONE_COLUMNS_NAME_MAP__
		];

		/**
		 * Executes the service.
		 *
		 * @param array $request the request parameters
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFieldException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\MethodNotAllowedException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public function execute(array $request = [])
		{
			// uncomment the next line to allow administrator only
			// Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$success = true;
			$error   = null;

			try {
				$this->executeSub($request);
			} catch (ORMException $e) {
				$success = false;
				$error   = $e;
			} catch (TypesInvalidValueException $e) {
				$success = false;
				$error   = $e;
			} catch (CRUDException $e) {
				$success = false;
				$error   = $e;
			}

			if (!$success) {
				$this->tryConvertException($error);
			}
		}

		/**
		 * Converts Gobl exceptions unto OZone exceptions.
		 *
		 * @param \Exception $error the exception
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InvalidFieldException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		public static function tryConvertException(\Exception $error)
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
				$data["field"] = $debug["field"];

				throw new InvalidFieldException($error->getMessage(), $data, $error);
			}

			throw $error;
		}

		/**
		 * Checks for request extra.
		 *
		 * @return bool
		 */
		private function noExtra()
		{
			return empty(URIHelper::getUriExtra());
		}

		/**
		 * Execute request with REST API in minds.
		 *
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\MethodNotAllowedException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		private function executeSub(array $request)
		{
			if ($this->noExtra()) {
				if (RequestHandler::isPost()) {
					$this->actionCreateEntity($request);
				} elseif (RequestHandler::isDelete()) {
					$this->actionDeleteAll($request);
				} elseif (RequestHandler::isGet()) {
					$this->actionGetAll($request);
				} elseif (RequestHandler::isPatch()) {
					$this->actionUpdateAll($request);
				} else {
					throw new MethodNotAllowedException();
				}
			} elseif ($extra = $this->getIdentifiedExtra()) {
				if (RequestHandler::isDelete()) {
					$this->actionDeleteEntity($extra);
				} elseif (RequestHandler::isGet()) {
					$this->actionGetEntity($request, $extra);
				} elseif (RequestHandler::isPatch()) {
					$this->actionUpdateEntity($request, $extra);
				} else {
					throw new MethodNotAllowedException();
				}
			} elseif ($extra = $this->getIdentifiedRelationExtra()) {
				if (RequestHandler::isPost()) {
					$this->actionAddRelation($request, $extra);
				} elseif (RequestHandler::isDelete()) {
					$this->actionDeleteRelation($request, $extra);
				} elseif (RequestHandler::isGet()) {
					$this->actionGetRelation($request, $extra);
				} elseif (RequestHandler::isPatch()) {
					$this->actionUpdateRelation($request, $extra);
				} else {
					throw new MethodNotAllowedException();
				}
			} else {
				// invalid url
				throw new NotFoundException();
			}
		}

		/**
		 * Gets identified extra.
		 *
		 * @return array|bool
		 */
		private function getIdentifiedExtra()
		{
			$extra_map = ['my_pk_column_const'];
			$extra     = [];
			$extra_ok  = URIHelper::parseUriExtra(self::$identified_extra_reg, $extra_map, $extra);

			if ($extra_ok) {
				return $extra;
			}

			return false;
		}

		/**
		 * Gets identified relation extra.
		 *
		 * @return array|bool
		 */
		private function getIdentifiedRelationExtra()
		{
			$extra_map = ['my_pk_column_const', 'relation'];
			$extra     = [];
			$extra_ok  = URIHelper::parseUriExtra(self::$identified_relation_extra_reg, $extra_map, $extra);

			if ($extra_ok) {
				return $extra;
			}

			return false;
		}

		//========================================================
		//=	POST REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		public function actionCreateEntity(array $request)
		{
			$form_values = self::onlyColumns(self::$columns_map, $request);

			$controller = new MyController();
			$entity     = $controller->addItem($form_values);

			$this->getResponseHolder()
				 ->setDone($controller->getCrud()->getMessage())
				 ->setData(['item' => $entity]);
		}

		/**
		 * @param array $request
		 * @param array $extra
		 */
		public function actionAddRelation(array $request, array $extra)
		{
			// TODO
		}

		//========================================================
		//=	PATCH REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionUpdateEntity(array $request, array $extra)
		{
			$form_values = self::onlyColumns(self::$columns_map, $request);

			$controller = new MyController();
			$entity     = $controller->updateOneItem($extra, $form_values);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone($controller->getCrud()->getMessage())
					 ->setData(['item' => $entity]);
			} else {
				throw new NotFoundException();
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionUpdateAll($request = [])
		{
			$data        = (isset($request["data"]) AND is_array($request["data"])) ? $request["data"] : [];
			$form_values = self::onlyColumns(self::$columns_map, $data);

			if (empty($form_values)) {
				throw new InvalidFormException();
			}

			$filters = self::getRequestFilters(self::$columns_map, $request);

			$controller = new MyController();
			$count      = $controller->updateAllItems($filters, $data);

			$this->getResponseHolder()
				 ->setDone($controller->getCrud()->getMessage())
				 ->setData(['affected' => $count]);
		}

		/**
		 * @param array $request
		 * @param array $extra
		 */
		public function actionUpdateRelation(array $request, array $extra)
		{
			// TODO
		}

		//========================================================
		//=	DELETE REQUEST METHODS
		//========================================================

		/**
		 * @param array $extra
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionDeleteEntity(array $extra)
		{

			$controller = new MyController();
			$entity     = $controller->deleteOneItem($extra);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone($controller->getCrud()->getMessage())
					 ->setData(['item' => $entity]);
			} else {
				throw new NotFoundException();
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionDeleteAll($request = [])
		{
			$filters = self::onlyColumns(self::$columns_map, $request);

			$controller = new MyController();
			$count      = $controller->deleteAllItems($filters);

			$this->getResponseHolder()
				 ->setDone($controller->getCrud()->getMessage())
				 ->setData(['affected' => $count]);
		}

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @thows \Gobl\DBAL\Exceptions\DBALException
		 */
		public function actionDeleteRelation(array $request, array $extra)
		{
			// TODO
		}

		//========================================================
		//=	GET REQUEST METHODS
		//========================================================

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionGetEntity(array $request, array $extra)
		{
			$controller = new MyController();
			$entity     = $controller->getItem($extra);

			if (!$entity) {
				throw new NotFoundException();
			}

			$relations = $this->listEntityRelations($entity, $request);

			$this->getResponseHolder()
				 ->setDone($controller->getCrud()->getMessage())
				 ->setData([
					 'item'      => $entity,
					 'relations' => $relations
				 ]);
		}

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function actionGetAll(array $request)
		{
			$filters  = self::getRequestFilters(self::$columns_map, $request);
			$order_by = self::getRequestOrderBy(self::$columns_map, $request);

			$p9            = self::getPagination($request);
			$max           = $p9["max"];
			$offset        = $p9["offset"];
			$page          = $p9["page"];
			$total_records = 0;

			$controller = new MyController();
			$results    = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
			$relations  = [];

			if (count($results)) {
				$relations = $this->listEntitiesRelations($relations, $request);
			}

			$this->getResponseHolder()
				 ->setDone($controller->getCrud()->getMessage())
				 ->setData([
					 'items'     => $results,
					 'max'       => $max,
					 'page'      => $page,
					 'total'     => $total_records,
					 'relations' => $relations
				 ]);
		}

		/**
		 * @param array $request
		 * @param array $extra
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		public function actionGetRelation(array $request, array $extra)
		{
			if (!isset($extra['my_pk_column_const'])) {
				throw new NotFoundException();
			}

			$filters['my_pk_column_const'] = $extra['my_pk_column_const'];
			$req_rel_name                  = $extra["relation"];
			$request["relations"]          = $req_rel_name;

			$controller = new MyController();
			$entity     = $controller->getItem($filters);

			if (!$entity) {
				throw new NotFoundException();
			}

			$p9            = self::getPagination($request);
			$max           = $p9["max"];
			$offset        = $p9["offset"];
			$page          = $p9["page"];
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
			$target_columns     = $relation->getTargetTable()->getColumns();

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
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function resolveRelations(array $request_relations)
		{
			$table   = ORM::getDatabase()->getTable(MyEntity::TABLE_NAME);
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

			// check if there are missing relations
			if (count($missing)) {
				throw new NotFoundException(null, ['RELATIONS_MISSING', $missing]);
			}

			return $rel_map;
		}
	}