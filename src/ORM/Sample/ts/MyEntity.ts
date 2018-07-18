export type tMyEntityData = {};

export class MyEntity extends GoblEntity {
	//__GOBL_TS_COLUMNS_CONST__
	constructor(data: tMyEntityData) {
		super(data, MyEntity.PREFIX, MyEntity.COLUMNS);
	}
	hydrate(data: tMyEntityData): this {
		super._hydrate(data);
		return this;
	}
	//__GOBL_TS_COLUMNS_GETTERS_SETTERS__
}