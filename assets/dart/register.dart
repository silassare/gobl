//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
// Auto generated file
//
// WARNING: please don't edit.
//
// Proudly With: <%$.gobl_version%>
// Time: <%$.gobl_time%>
//@<%}%>
import 'package:gobl_utils_dart/gobl_utils_dart.dart';
import './bundle.dart';

void register() {
  Gobl()//@<%loop($.entities : $entity){%>
  ..register(name: <%$entity%>.ENTITY_NAME, columns: <%$entity%>.COLUMNS, entity: <%$entity%>)//@<%}%>;
}
