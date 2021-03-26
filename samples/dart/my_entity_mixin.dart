//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
// Auto generated file
//
// INFO: you are free to edit it,
// but make sure to know what you are doing.
//
// Proudly With: <%$.gobl_version%>
// Time: <%$.gobl_time%>
//@<%}%>

import '../base/my_entity_base.dart';
import '../entities/my_entity.dart';

mixin MyEntityMixin<T extends MyEntity> on MyEntityBase {
  T get self;

  //====================================================
  //=	Your custom implementation goes here
  //====================================================
}
