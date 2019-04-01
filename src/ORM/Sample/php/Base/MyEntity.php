<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\ORM\ORMEntityBase;
	use MY_PROJECT_DB_NS\MyTableQuery as MyTableQueryReal;

	//__GOBL_RELATIONS_USE_CLASS__

	/**
	 * Class MyEntity
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyEntity extends ORMEntityBase
	{
		const TABLE_NAME = 'my_table';

		//__GOBL_COLUMNS_CONST__
		//__GOBL_RELATIONS_PROPERTIES__

		/**
		 * MyEntity constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict Enable/disable strict mode
		 */
		public function __construct($is_new = true, $strict = true)
		{
			parent::__construct($is_new, $strict, MyEntity::TABLE_NAME, MyTableQueryReal::class);
		}
		//__GOBL_RELATIONS_GETTERS__
		//__GOBL_COLUMNS_GETTERS_SETTERS__
	}