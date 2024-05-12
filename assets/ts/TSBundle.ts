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

//@<%loop($.entities : $name : $content){%>register('<%$name%>', <%$name%>);
//@<%}%>

//@export {
//@<%loop($.entities : $name : $content){%><%$name%>,
//@<%}%>
//@};
