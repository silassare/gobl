//@<%if(@length($.gobl_header)){%><%$.gobl_header%><%} else {%>
/**
 * Auto generated file,
 *
 * INFO: you are free to edit it,
 * but make sure to know what you are doing.
 *
 * Proudly With: <%$.gobl_version%>
 * Time: <%$.gobl_time%>
 */
//@<%}%>
import { getEntityCache, type GoblEntityData } from "gobl-utils-ts";
import MyEntityBase from "./base/MyEntityBase";

export default class MyEntity extends MyEntityBase {
	constructor(data?: GoblEntityData) {
		super(data, "MyEntity", MyEntity.PREFIX, MyEntity.COLUMNS);
	}

	public static fromCache(cacheKey: string): MyEntity | undefined {
		const cache = getEntityCache("MyEntity");
		return cache && (cache[cacheKey] as MyEntity);
	}

	//====================================================
	//=	Your custom implementation goes here
	//====================================================
}
