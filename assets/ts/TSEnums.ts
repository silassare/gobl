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

//@<%loop($.enums : $name : $cases){%>
//@export enum <%$name%> {
//@<%loop($cases : $case : $value){%>
//@    <%$case%> = <%$value%>,
//@<%}%>
//@}
//@<%}%>

