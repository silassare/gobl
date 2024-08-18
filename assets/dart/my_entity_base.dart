//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
// Auto generated file
//
// WARNING: please don't edit.
//
// Proudly With: <%$.gobl_version%>
// Time: <%$.gobl_time%>
//@<%}%>
import 'package:gobl_utils_dart/gobl_utils_dart.dart';

abstract class MyEntityBase
    extends GoblEntity //@<%if(@length($.pk_columns) == 1){%>with GoblSinglePKEntity<%}%>
    {
      MyEntityBase({
        Map<String, dynamic> initialData = const {},
        String name,
        String prefix,
        List<String> columns
      })
          : super(
            initialData: initialData,
            name: name,
            prefix: prefix,
            columns: columns
            );
}
