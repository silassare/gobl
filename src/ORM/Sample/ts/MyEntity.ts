export class MyEntity extends GoblEntity {
	//__GOBL_TS_COLUMNS_CONST__
	constructor(data?: tGoblEntityData) {
		super(data, "MyEntity", MyEntity.PREFIX, MyEntity.COLUMNS);
	}
	//__GOBL_TS_COLUMNS_GETTERS_SETTERS__
}

register("MyEntity", MyEntity);