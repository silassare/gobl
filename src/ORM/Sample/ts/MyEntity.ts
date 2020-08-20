//__GOBL_HEAD_COMMENT__
import {
	getEntityCache,
	tGoblEntityData,
} from 'gobl-utils-ts';
import MyEntityBase from './base/MyEntityBase';

export default class MyEntity extends MyEntityBase {

	constructor(data?: tGoblEntityData) {
		super(data, 'MyEntity', MyEntity.PREFIX, MyEntity.COLUMNS);
	}

	public static fromCache(cacheKey: string): MyEntity | undefined {
		const cache: any = getEntityCache('MyEntity');
		return cache && cache[cacheKey];
	}
}
