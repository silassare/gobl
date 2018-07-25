export class MyEntity extends GoblEntity {
	//__GOBL_TS_COLUMNS_CONST__
	constructor(data:{[key:string]:any}) {
		super(data, "MyEntity", MyEntity.PREFIX, MyEntity.COLUMNS);
	}
	//__GOBL_TS_COLUMNS_GETTERS_SETTERS__
}
gobl.MyEntity = MyEntity;