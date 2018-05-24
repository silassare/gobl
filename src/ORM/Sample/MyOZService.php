<?php
//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_SERVICE_NS;

	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
	use Gobl\ORM\ORM;
	use MY_PROJECT_DB_NS\MyController;
	use MY_PROJECT_DB_NS\MyEntity;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\RequestHandler;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\BadRequestException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFieldException;
	use OZONE\OZ\Exceptions\InvalidFormException;
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
	 * to get item(s) from relation for the item in `my_table` with the given :my_id
	 * - GET     /my_svc/:my_id/relation
	 *
	 * @package MY_PROJECT_DB_NS\App\Services
	 */
	final class MyOZService extends BaseService
	{
		// for url like /my_svc/:my_id
		private static $IDENTIFIED_EXTRA_REG = '#^([a-zA-Z0-9]+)/?$#';

		// for url like /my_svc/:my_id/relation
		private static $IDENTIFIED_RELATION_EXTRA_REG = '#^([a-zA-Z0-9]+)/([a-zA-Z0-9_-]+)/?$#';

		/**
		 * maps form fields to columns
		 *
		 * @var array
		 */
		private static $my_table_fields_map = [
//__OZONE_COLUMNS_NAME_MAP__
		];

		/**
		 * maps form fields to columns
		 *
		 * @var array
		 */
		private static $my_table_relations_map = [
//__OZONE_RELATIONS_NAME_MAP__
		];

		/**
		 * Executes the service.
		 *
		 * @param array $request the request parameters
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
			} catch (ORMControllerFormException $e) {
				$success = false;
				$error   = $e;
			} catch (TypesInvalidValueException $e) {
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

			if ($error instanceof TypesInvalidValueException) {
				// don't expose debug data to client, may contains sensitive data
				$debug         = $error->getDebugData();
				$data          = $error->getData();
				$data["field"] = self::maskColumn($debug["column_name"]);

				throw new InvalidFieldException($error->getMessage(), $data, $error);
			}

			throw $error;
		}

		/**
		 * Execute request with REST API in minds.
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
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
				} else {
					throw new ForbiddenException();
				}
			} elseif ($extra = $this->getIdentifiedExtra()) {
				if (RequestHandler::isDelete()) {
					$this->actionDeleteEntity($request, $extra);
				} elseif (RequestHandler::isGet()) {
					$this->actionGetEntity($request, $extra);
				} elseif (RequestHandler::isPatch()) {
					$this->actionUpdateEntity($request, $extra);
				} else {
					throw new ForbiddenException();
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
					throw new ForbiddenException();
				}
			} else {
				// invalid url
				throw new NotFoundException();
			}
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
		 * Gets identified extra.
		 *
		 * @return array|bool
		 */
		private function getIdentifiedExtra()
		{
			$extra_map = ['my_pk_column_const'];
			$extra     = [];
			$extra_ok  = URIHelper::parseUriExtra(self::$IDENTIFIED_EXTRA_REG, $extra_map, $extra);

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
			$extra_ok  = URIHelper::parseUriExtra(self::$IDENTIFIED_RELATION_EXTRA_REG, $extra_map, $extra);

			if ($extra_ok) {
				return $extra;
			}

			return false;
		}

		/**
		 * Removes column mask.
		 *
		 * @param array $request
		 *
		 * @return array
		 */
		private static function removeColumnsMask(array $request)
		{
			$values = [];

			foreach ($request as $field => $value) {
				if (isset(self::$my_table_fields_map[$field])) {
					$column_name          = self::$my_table_fields_map[$field];
					$values[$column_name] = $value;
				}
			}

			return $values;
		}

		/**
		 * Mask column name.
		 *
		 * @param string $column_name the column name
		 *
		 * @return string
		 */
		private static function maskColumn($column_name)
		{
			$flip = array_flip(self::$my_table_fields_map);

			if (isset($flip[$column_name])) {
				return $flip[$column_name];
			}

			return $column_name;
		}

		/**
		 * Remove relation name mask.
		 *
		 * @param string $relation_name
		 *
		 * @return string
		 */
		private static function removeRelationMask($relation_name)
		{
			if (isset(self::$my_table_relations_map[$relation_name])) {
				return self::$my_table_relations_map[$relation_name];
			}

			return $relation_name;
		}

		/**
		 * Returns result pagination.
		 *
		 * @param array $request
		 * @param int   $max default max
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\BadRequestException
		 */
		private static function getPagination(array $request, $max = 10)
		{
			$offset = 0;
			$page   = 1;

			if (isset($request['max'])) {
				$max = intval($request['max']);
				if (!is_int($max) OR $max <= 0) {
					throw new BadRequestException();
				}
			}

			if (isset($request['page'])) {
				$page = intval($request['page']);
				if (!is_int($page) OR $page <= 0) {
					throw new BadRequestException();
				}

				$offset = ($page - 1) * $max;
			}

			return [
				"offset" => $offset,
				"max"    => $max,
				"page"   => $page
			];
		}

//========================================================
//=	POST REQUEST METHOD
//========================================================

		public function actionCreateEntity(array $request)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$form_values = self::removeColumnsMask($request);

			// prevent non-null value on auto-increment column
			$form_values['my_pk_column_const'] = null;

			$controller = new MyController();
			$entity     = $controller->addItem($form_values);

			$this->getResponseHolder()
				 ->setDone('CREATED')
				 ->setData(['item' => $entity->asArray()]);
		}

		public function actionAddRelation(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			// TODO
			$this->getResponseHolder()
				 ->setDone('RELATION_ADDED')
				 ->setData(['added relation', $request, $extra]);
		}

//========================================================
//=	PUT REQUEST METHOD
//========================================================

		public function actionUpdateEntity(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$form_values = self::removeColumnsMask($request);

			// primary key value should not be updated
			unset($form_values['my_pk_column_const']);

			$controller = new MyController();
			$entity     = $controller->updateOneItem($extra, $form_values);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone('UPDATED')
					 ->setData(['item' => $entity->asArray()]);
			} else {
				throw new NotFoundException();
			}
		}

		public function actionUpdateRelation(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			// TODO
			$this->getResponseHolder()
				 ->setDone('UPDATED')
				 ->setData(['updated relation', $request, $extra]);
		}

//========================================================
//=	DELETE REQUEST METHOD
//========================================================

		public function actionDeleteEntity(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$controller = new MyController();
			$entity     = $controller->deleteOneItem($extra);

			if ($entity instanceof MyEntity) {
				$this->getResponseHolder()
					 ->setDone('DELETED')
					 ->setData(['item' => $entity->asArray()]);
			} else {
				throw new NotFoundException();
			}
		}

		public function actionDeleteAll($request = [])
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$controller = new MyController();
			$count      = $controller->deleteAllItems($request);

			$this->getResponseHolder()
				 ->setDone('DELETED')
				 ->setData(['affected' => $count]);
		}

		public function actionDeleteRelation(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			// TODO
			$this->getResponseHolder()
				 ->setDone('DELETED')
				 ->setData(['deleted relation items count', $request, $extra]);
		}

//========================================================
//=	GET REQUEST METHOD
//========================================================

		public function actionGetEntity(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$controller = new MyController();
			$entity     = $controller->getItem($extra);

			if (!$entity) {
				throw new NotFoundException();
			}

			$this->getResponseHolder()
				 ->setDone()
				 ->setData(['item' => $entity->asArray()]);
		}

		public function actionGetAll(array $request)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$filters = (isset($request['filters']) AND is_array($request['filters'])) ? $request['filters'] : [];

			$p9            = self::getPagination($request);
			$max           = $p9["max"];
			$offset        = $p9["offset"];
			$page          = $p9["page"];
			$total_records = 0;

			$controller = new MyController();
			$results    = $controller->getAllItems($filters, $max, $offset, [], $total_records);

			$this->getResponseHolder()
				 ->setDone()
				 ->setData([
					 'items' => $results,
					 'max'   => $max,
					 'page'  => $page,
					 "total" => $total_records
				 ]);
		}

		public function actionGetRelation(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			if (!isset($extra['my_pk_column_const'])) {
				throw new NotFoundException();
			}

			$filters['my_pk_column_const'] = $extra['my_pk_column_const'];
			$controller                    = new MyController();
			$entity                        = $controller->getItem($filters);

			if (!$entity) {
				throw new NotFoundException();
			}

			$relation_name = self::removeRelationMask($extra["relation"]);
			$table         = ORM::getDatabase()
								->getTable(MyEntity::TABLE_NAME);

			if (!$table->hasRelation($relation_name)) {
				throw new NotFoundException();
			}

			$rel      = $table->getRelation($relation_name);
			$rel_type = $rel->getType();

			if ($rel_type === Relation::ONE_TO_MANY OR $rel_type === Relation::MANY_TO_MANY) {
				$results = self::getRelationItemsList($rel, $entity, $request, $extra);
			} else {
				$results = self::getRelationItem($rel, $entity, $extra);
			}

			return $this->getResponseHolder()
						->setDone()
						->setData($results);
		}

		public static function getRelationItemsList(Relation $relation, MyEntity $entity, array $request, array $extra)
		{
			$filters  = (isset($request['filters']) AND is_array($request['filters'])) ? $request['filters'] : [];
			$order_by = (isset($request['order_by']) AND is_array($request['order_by'])) ? $request['order_by'] : [];

			$p9            = self::getPagination($request);
			$max           = $p9["max"];
			$offset        = $p9["offset"];
			$page          = $p9["page"];
			$total_records = 0;

			$relation_getter = $relation->getGetterName();
			$items           = call_user_func_array([
				$entity,
				$relation_getter
			], [$filters, $max, $offset, $order_by, &$total_records]);

			$relations[$extra['relation']] = $items;

			return [
				'item'      => $entity,
				'relations' => $relations,
				'max'       => $max,
				'page'      => $page,
				'total'     => $total_records
			];
		}

		public static function getRelationItem(Relation $relation, MyEntity $entity, array $extra)
		{
			$relation_getter               = $relation->getGetterName();
			$item                          = call_user_func([$entity, $relation_getter]);
			$relations[$extra['relation']] = $item;

			return [
				'item'      => $entity,
				'relations' => $relations
			];
		}
	}