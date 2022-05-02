//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
// Auto generated file
//
// WARNING: please don't edit.
//
// Proudly With: <%$.gobl_version%>
// Time: <%$.gobl_time%>
//@<%}%>
import '../base/my_entity_base.dart';
import '../mixins/my_entity_mixin.dart';

class MyEntity extends MyEntityBase with MyEntityMixin<MyEntity> {
  static const ENTITY_NAME = 'MyEntity';
  static const PREFIX = '<%$.columns_prefix%>';
  static const COLUMNS = [
//@  <%loop($.columns : $column){%> '<%$column.fullName%>',
//@  <%}%>
  ];
//@  <%loop($.columns : $column){%> static const <%$column.const%> = '<%$column.fullName%>';
//@<%}%>

  MyEntity([Map<String, dynamic> initialData = const {}])
      : super(
      initialData: initialData,
      name: ENTITY_NAME,
      prefix: PREFIX,
      columns: COLUMNS);

  @override
  MyEntity get self {
    return this;
  }
//@<%@var $len = @length($.pk_columns);%><%if($len == 1){%>
  @override
  String singlePKValue() {
    return '$<%$.pk_columns[0].name%>';
  }
//@<%}%>

  @override
  List<String> identifierColumns() {
    return [
//@<%if($len){%>
//@    <%loop($.pk_columns : $pk){%>    <%$pk.const%><%@if($len!==1, ',' , '')%> <%}%>
//@<%} else {%>    ...COLUMNS <%}%>
    ];
  }

  @override
  MyEntity fromJson(Map<String, dynamic> json) {
    return MyEntity(json);
  }
//@  <%loop($.columns : $column){%>
//@  <%@join($column.readTypeHint, '|')%> get <%$column.name%> { return doGet(<%$column.const%>, <%@join($column.readTypeHint, '|')%>); }
//@  set <%$column.name%>(<%@join($column.writeTypeHint, '|')%> nVal) { doSet(<%$column.const%>, nVal, <%@join($column.readTypeHint, '|')%>); }
//@  <%@join($column.readTypeHint, '|')%> get<%$column.methodSuffix%>() { return <%$column.name%>; }
//@  MyEntity set<%$column.methodSuffix%>(<%@join($column.writeTypeHint, '|')%> nVal) { <%$column.name%> = nVal; return this; }
//@ <%}%>
}
