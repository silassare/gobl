//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
// Auto generated file
//
// WARNING: please don't edit.
//
// Proudly With: <%$.gobl_version%>
// Time: <%$.gobl_time%>
//@<%}%>
import 'package:gobl_utils_dart/gobl_utils_dart.dart';

//@<%loop($.entities : $name : $content){%>export './db/entities/<%$name%>.dart';
//@<%}%>
