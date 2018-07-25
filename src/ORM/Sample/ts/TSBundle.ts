//__GOBL_HEAD_COMMENT__

const win: any = window,
	gobl: any = win.gobl = win.gobl || {},
	_hasOwn = Object.hasOwnProperty,
	_isPlainObject = (a: any): boolean => Object.prototype.toString.call(a) === "[object Object]",
	_c_bool = (v: any): boolean => {
		// @ts-ignore
		return v !== null ? Boolean(v) : null;
	},
	_c_int = (v: any): number => {
		// @ts-ignore
		return v !== null ? parseInt(v) : null;
	},
	_c_string = (v: any): string => {
		// @ts-ignore
		return v !== null ? String(v) : null;
	},
	gobl_marker = "$_gobl_marker",
	gobl_class_magic_map: { [key: string]: string } = {},
	ACTION_UNKNOWN = 0,
	ACTION_SAVING = 1,
	ACTION_DELETING = 2,
	ACTION_UPDATING = 3,
	realJSONParse = JSON.parse,
	goblJSONParse = function (text: any, reviver?:(key: any, value: any)=> any) {
		return realJSONParse(text, function (key, value) {
			if (typeof reviver === "function") {
				value = reviver(key, value);
			}

			if (_isPlainObject(value)) {
				let i = GoblEntity.toInstance(value);
				if (i) {
					return i;
				}
			}

			return value;
		});
	};

// use only camelCase method name don't use `getSomething` or `setSomething`
export class GoblEntity {
	protected readonly _data: any = {};
	protected _cache: any = {};
	protected _action: number = ACTION_UNKNOWN;

	constructor(_initial_data: any = {}, private readonly _name: string, private readonly _prefix: string, private readonly _columns: string[]) {
		let ctx = this;

		_columns.forEach(function (col) {
			ctx._data[col] = ctx._cache[col] = (col in _initial_data) ? _initial_data[col] : undefined;
		});
	}

	protected _set(column: string, value: any): this {
		this._data[column] = value;
		return this;
	}

	isClean(): boolean {
		return Object.keys(this.toObject(true)).length === 0
	}

	isSaved(set?: boolean): boolean {
		if (set) {
			this._cache = {};
			return true;
		}

		return this.isClean()
	}

	isSaving(set?: boolean): boolean {
		if (arguments.length) {
			this._action = set ? ACTION_SAVING : ACTION_UNKNOWN;
		}

		return this._action === ACTION_SAVING;
	}

	isDeleting(set?: boolean): boolean {
		if (arguments.length) {
			this._action = set ? ACTION_DELETING : ACTION_UNKNOWN;
		}

		return this._action === ACTION_DELETING;
	}

	isUpdating(set?: boolean): boolean {
		if (arguments.length) {
			this._action = set ? ACTION_UPDATING : ACTION_UNKNOWN;
		}

		return this._action === ACTION_UPDATING;
	}

	// hydrate could be a cool name but an entity
	// could have `[prefix_]hydrate` as his column name
	// so we use camelCase method name
	// hydrate the entity and save the state when save is true
	doHydrate(data: any, save: boolean = false): this {
		let ctx = this,
			source_of_truth = this._data;
		Object.keys(data).forEach(function (k) {
			if (k in source_of_truth) {
				(ctx as any)[k.slice(ctx._prefix.length + 1)] = data[k];
			}
		});

		if (save) {
			this.isSaved(true);
		}

		return this;
	}

	// returns current data in a clean new object
	// if diff is true, returns modified columns only
	toObject(diff: boolean = false) {
		let o: any = {};

		if (diff) {
			for (let k in this._cache) {
				if (_hasOwn.call(this._cache, k)) {
					if (this._cache[k] !== this._data[k]) {
						o[k] = this._data[k];
					}
				}
			}
			return o;
		}

		for (let k in this._data) {
			if (_hasOwn.call(this._data, k)) {
				o[k] = this._data[k];
			}
		}

		return o;
	}

	// returns some column values
	toObjectSome(columns: string[]) {
		let o: any = {}, len = columns.length;

		for (let i = 0; i < len; i++) {
			let col = columns[i];
			if (col in this._data) {
				o[col] = this._data[col];
			} else {
				throw new Error(`Column "${col}" is not defined in "${this._name}".`);
			}
		}

		return o;
	}

	// JSON helper
	toJSON(): string {
		let data = this.toObject();
		data[gobl_marker] = this._name;

		return JSON.stringify(data);
	}

	static toInstance(data: any): GoblEntity | undefined {
		if (_isPlainObject(data)) {
			let entity_name = data[gobl_marker],
				entity, magic;

			if (entity_name) {
				entity = gobl[entity_name];
			} else {
				magic = Object.keys(data).sort().join("");
				entity_name = gobl_class_magic_map[magic];

				if (entity_name) {
					entity = gobl[entity_name];
				}
			}

			if (entity) {
				return new entity(data);
			}
		}

		return undefined;
	}
}

export abstract class GoblEntityWithId extends GoblEntity {
	abstract getId(): string
}

//__GOBL_TS_ENTITIES_CLASS_LIST__

Object.keys(gobl).forEach(function (entity) {
	gobl_class_magic_map[gobl[entity]["COLUMNS"].sort().join("")] = entity;
});

JSON.parse = goblJSONParse;

console.log("[gobl] ready!");