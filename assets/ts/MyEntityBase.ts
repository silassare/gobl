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
import {GoblEntity} from 'gobl-utils-ts';

export default abstract class MyEntityBase extends GoblEntity {
	public static readonly PREFIX: string = '<%$.columns_prefix%>';
	public static readonly COLUMNS: string[] = [
//@	<%loop($.columns : $column){%>	'<%$column.fullName%>',
//@		<%}%>
	];
//@<%loop($.columns : $column){%>	public static readonly <%$column.const%>: string = '<%$column.fullName%>';
//@<%}%>
//@<%if(@length($.pk_columns) == 1){%>
//@	public singlePKValue():string {
//@		const id = this.<%$.pk_columns[0].name%>;
//@		if (id === undefined || id === null) {
//@			throw new Error('Cannot get single PK value for unsaved <%$.class.entity%>.');
//@		}
//@		return String(id);
//@	}<%}%>
//@<%if(@length($.pk_columns)){%>
//@	public identifierColumns(): string[] {
//@		return [ <%loop($.pk_columns : $pk){%><%$.class.entity%>Base.<%$pk.const%><%@if(@length($.pk_columns)!==1, ',' , '')%> <%}%>];
//@	}<%} else {%>
//@	public identifierColumns() {
//@		return <%$.class.entity%>Base.COLUMNS;
//@	}<%}%>
//@<%loop($.columns : $column){%>
//@	get <%$column.name%>(): <%$column.readTypeHintSaved%> { return this._data[<%$.class.entity%>Base.<%$column.const%>]; }
//@	set <%$column.name%>(nVal: <%$column.writeTypeHint%>) { this._set(<%$.class.entity%>Base.<%$column.const%>, nVal); }
////@	public get<%$column.methodSuffix%>() { return this.<%$column.name%>; }
////@	public set<%$column.methodSuffix%>(nVal: <%$column.writeTypeHint%>): this { this.<%$column.name%> = nVal; return this; }
//@<%}%>

}
