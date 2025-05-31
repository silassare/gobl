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

//@import { register } from 'gobl-utils-ts';

//@<%loop($.entities : $name : $content){%>import <%$name%> from './db/<%$name%>';
//@<%}%>

// START: ENTITIES REGISTRATION
//@<%loop($.entities : $name : $content){%>register('<%$name%>', <%$name%>);
//@<%}%>
// END: ENTITIES REGISTRATION

// START: ENUMS EXPORT
//@<%loop($.enums : $name : $cases){%>
//@export enum <%$name%> {
//@<%loop($cases : $case : $value){%>
//@    <%$case%> = <%$value%>,
//@<%}%>
//@}
//@<%}%>
// END: ENUMS EXPORT

//@export {
//@<%loop($.entities : $name : $content){%><%$name%>,
//@<%}%>
//@};
