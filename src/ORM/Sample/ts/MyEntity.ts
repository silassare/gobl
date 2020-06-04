//__GOBL_HEAD_COMMENT__
import {
	GoblEntity,
	getEntityCache,
	_bool,
	_int,
	_string,
	tGoblEntityData,
} from 'gobl-utils-ts';

export default class MyEntity extends GoblEntity {
	//__GOBL_TS_COLUMNS_CONST__
	public static fromCache(cacheKey: string): MyEntity | undefined {
		const cache: any = getEntityCache('MyEntity');
		return cache && cache[cacheKey];
	}

	constructor(data?: tGoblEntityData) {
		super(data, 'MyEntity', MyEntity.PREFIX, MyEntity.COLUMNS);
	}
//__GOBL_TS_COLUMNS_GETTERS_SETTERS__
}
