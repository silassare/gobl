<?php
//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
/**
 * Auto generated file
 *
 * WARNING: please don't edit.
 *
 * Proudly With: <%$.gobl_version%>
 * Time: <%$.gobl_time%>
 */
//@<%}%>

namespace MY_DB_NS\Base;

use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntityBase;
use MY_DB_NS\MyTableQuery as MyTableQueryReal;

//@<%loop($.relations.use : $class){%>use <%$class%>;
//@<%}%>

/**
 * Class MyEntity
 */
abstract class MyEntity extends ORMEntityBase
{
	const TABLE_NAME = 'my_table';
	//@<%loop($.columns : $column){%>
	//@const <%$column.const%> = '<%$column.fullName%>';<%}%>

	//@<%loop($.relations.list : $relation){%>
	//@<%if($relation.type === 'one-to-one' || $relation.type === 'many-to-one'){%>
	//@/**
	//@ * @var \<%$relation.target.class.use_entity%>
	//@ */
	//@protected $_r_<%$relation.name%>;
	//@<%}%>
	//@<%}%>

	/**
	 * MyEntity constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct($is_new = true, $strict = true)
	{
		parent::__construct(
			ORM::getDatabase('MY_DB_NS'),
			$is_new,
			$strict,
			self::TABLE_NAME,
			MyTableQueryReal::class
		);
	}
	//@<%loop($.relations.list : $relation){%>
//@<%if($relation.type === 'one-to-one'){%>
//@	/**
//@	 * OneToOne relation between `<%$relation.host.table.name%>` and `<%$relation.target.table.name%>`.
//@	 *
//@	 * @return null|\<%$relation.target.class.use_entity%>
//@	 * @throws \Throwable
//@	 */
//@	public function get<%$relation.methodSuffix%>()
//@	{
//@		if (!isset($this->_r_<%$relation.name%>)) {
//@			$filters = [];<%loop ($relation.filters : $filter){%>
//@			if(!is_null($v = $this->get<%$filter.to.methodSuffix%>())){
//@				$filters['<%$filter.from.fullName%>'] = $v;
//@			}<%}%>
//@			if (empty($filters)){
//@				return null;
//@			}
//@
//@			$m = new <%$relation.target.class.controller%>RealR();
//@			$this->_r_<%$relation.name%> = $m->getItem($filters);
//@		}
//@
//@		return $this->_r_<%$relation.name%>;
//@	}
//@<%} else if($relation.type === 'one-to-many'){%>
//@	/**
//@	 * OneToMany relation between `<%$relation.host.table.name%>` and `<%$relation.target.table.name%>`.
//@	 *
//@	 * @param array	$filters  the row filters
//@	 * @param int|null $max	  maximum row to retrieve
//@	 * @param int	  $offset   first row offset
//@	 * @param array	$order_by order by rules
//@	 * @param int|bool $total	total rows without limit
//@	 *
//@	 * @return \<%$relation.target.class.use_entity%>[]
//@	 * @throws \Throwable
//@	 */
//@	function get<%$relation.methodSuffix%>($filters = [], $max = null, $offset = 0, $order_by = [], &$total = false)
//@	{<%loop ($relation.filters : $filter){%>
//@		if(!is_null($v = $this->get<%$filter.to.methodSuffix%>())){
//@			$filters['<%$filter.from.fullName%>'] = $v;
//@		}<%}%>
//@		if (empty($filters)){
//@			return [];
//@		}
//@
//@		$ctrl = new <%$relation.target.class.controller%>RealR();
//@
//@		return $ctrl->getAllItems($filters, $max, $offset, $order_by, $total);
//@	}
//@<%} else if($relation.type === 'many-to-many'){%>
//@	/**
//@	 * ManyToMany relation between `<%$relation.host.table.name%>` and `<%$relation.target.table.name%>`.
//@	 *
//@	 * @param array	$filters  the row filters
//@	 * @param int|null $max	  maximum row to retrieve
//@	 * @param int	  $offset   first row offset
//@	 * @param array	$order_by order by rules
//@	 * @param int|bool $total	total rows without limit
//@	 *
//@	 * @return \<%$relation.target.class.use_entity%>[]
//@	 * @throws \Throwable
//@	 */
//@	function get<%$relation.methodSuffix%>($filters = [], $max = null, $offset = 0, $order_by = [], &$total = false)
//@	{<%loop ($relation.filters : $filter){%>
//@		if(!is_null($v = $this->get<%$filter.to.methodSuffix%>())){
//@			$filters['<%$filter.from.fullName%>'] = $v;
//@		}<%}%>
//@		if (empty($filters)){
//@			return null;
//@		}
//@
//@		$ctrl = new <%$relation.target.class.controller%>RealR();
//@
//@		return $ctrl->getAllItems($filters, $max, $offset, $order_by, $total);
//@	}
//@<%} else if($relation.type === 'many-to-one'){%>
//@	/**
//@	 * ManyToOne relation between `<%$relation.host.table.name%>` and `<%$relation.target.table.name%>`.
//@	 *
//@	 * @return null|\<%$relation.target.class.use_entity%>
//@	 * @throws \Throwable
//@	 */
//@	public function get<%$relation.methodSuffix%>()
//@	{
//@		if (!isset($this->_r_<%$relation.name%>)) {
//@			$filters = [];<%loop ($relation.filters : $filter){%>
//@			if(!is_null($v = $this->get<%$filter.to.methodSuffix%>())){
//@				$filters['<%$filter.from.fullName%>'] = $v;
//@			}<%}%>
//@			if (empty($filters)){
//@				return null;
//@			}
//@
//@			$m = new <%$relation.target.class.controller%>RealR();
//@			$this->_r_<%$relation.name%> = $m->getItem($filters);
//@		}
//@
//@		return $this->_r_<%$relation.name%>;
//@	}
//@<%}%><%}%>
//@	<%loop($.columns : $column){%>
//@	/**
//@	 * Getter for column `<%$.table.name%>`.`<%$column.name%>`.
//@	 *
//@	 * @return <%$column.returnType%> the real type is: <%$column.columnType%>
//@	 */
//@	public function get<%$column.methodSuffix%>()
//@	{
//@		$column = self::<%$column.const%>;
//@		$v = $this->$column;
//@
//@		if( $v !== null){
//@			$v = (<%$column.returnType%>)$v;
//@		}
//@
//@		return $v;
//@	}
//@
//@	/**
//@	 * Setter for column `<%$.table.name%>`.`<%$column.name%>`.
//@	 *
//@	 * @param <%$column.argType%> $<%$column.argName%>
//@	 *
//@	 * @return static
//@	 */
//@	public function set<%$column.methodSuffix%>($<%$column.argName%>)
//@	{
//@		$column = self::<%$column.const%>;
//@		$this->$column = $<%$column.argName%>;
//@
//@		return $this;
//@	}
//@<%}%>
}
