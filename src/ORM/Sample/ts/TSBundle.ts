//__GOBL_HEAD_COMMENT__

const _c_bool   = (v: any): boolean => {
		  return v !== null ? Boolean(v) : null;
	  },
	  _c_int    = (v: any): number => {
		  return v !== null ? parseInt(v) : null;
	  },
	  _c_string = (v: any): string => {
		  return v !== null ? String(v) : null;
	  };

export class GoblEntity {
	protected readonly _data = {};

	constructor(original_data = {}, private readonly prefix, private readonly columns) {
		let ctx = this;
		columns.forEach(function (col) {
			ctx._data[col]  = (col in original_data) ? original_data[col] : undefined;
		});
	}

	protected _hydrate(data: any):this {
		let ctx = this,
		source_of_truth = this._data;
		Object.keys(data).forEach(function (k) {
			if (k in source_of_truth) {
				ctx[k.slice(ctx.prefix.length + 1)] = data[k];
			}
		});

		return this;
	}

	toJSON(): string {
		return JSON.stringify(this._data);
	}
}

//__GOBL_TS_ENTITIES_CLASS_LIST__