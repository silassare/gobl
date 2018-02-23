<?php
//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_SERVICE_NS;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
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
//__OZONE_FIELDS_TO_COLUMNS_MAP__
		];

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
				// TODO change message to ozone error message, add the invalid field name in data
				if ($error instanceof ORMControllerFormException) {
					throw new InvalidFormException($error->getMessage(), $error->getData(), $error);
				}
				if ($error instanceof TypesInvalidValueException) {
					throw new InvalidFieldException($error->getMessage(), $error->getData(), $error);
				}
			}
		}

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

		private function noExtra()
		{
			return empty(URIHelper::getUriExtra());
		}

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
		 * @param array $request
		 *
		 * @return array
		 */
		private function requestFieldsToColumns(array $request)
		{
			$values = [];

			foreach ($request as $field => $value) {
				if (isset(self::$my_table_fields_map[$field])) {
					$column          = self::$my_table_fields_map[$field];
					$values[$column] = $value;
				}
			}

			return $values;
		}

//========================================================
//=	POST REQUEST METHOD
//========================================================

		private function actionCreateEntity(array $request)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$form_values = self::requestFieldsToColumns($request);

			// prevent non-null value on auto-increment column
			$form_values['my_pk_column_const'] = null;

			$controller = new MyController();
			$entity     = $controller->addItem($form_values);

			$this->getResponseHolder()
				 ->setDone('CREATED')
				 ->setData(['item' => $entity->asArray()]);
		}

		private function actionAddRelation(array $request, array $extra)
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

		private function actionUpdateEntity(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$form_values = self::requestFieldsToColumns($request);

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

		private function actionUpdateRelation(array $request, array $extra)
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

		private function actionDeleteEntity(array $request, array $extra)
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

		private function actionDeleteAll($request = [])
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$controller = new MyController();
			$count      = $controller->deleteAllItem($request);

			$this->getResponseHolder()
				 ->setDone('DELETED')
				 ->setData(['affected' => $count]);
		}

		private function actionDeleteRelation(array $request, array $extra)
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

		private function actionGetEntity(array $request, array $extra)
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

		private function actionGetAll(array $request)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			$filters = isset($request['filters']) ? $request['filters'] : [];
			$max     = 10;
			$offset  = 0;
			$page    = 1;

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

			$controller = new MyController();
			$results    = $controller->getAllItems($filters, $max, $offset);

			$this->getResponseHolder()
				 ->setDone()
				 ->setData(['items' => $results, 'max' => $max, 'page' => $page]);
		}

		private function actionGetRelation(array $request, array $extra)
		{
			// uncomment the next line to allow administrator only
			Assert::assertIsAdmin();
			// or uncomment the next line to allow verified user only
			// Assert::assertUserVerified();

			// TODO
			$this->getResponseHolder()
				 ->setDone()
				 ->setData(['item' => $extra, 'relations' => ["relation" => $request]]);
		}
	}