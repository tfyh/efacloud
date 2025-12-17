/**
 * a table is an object of table row objects, indexed by the uid and defined by
 * configuration.
 */

class TfyhTable {
	
	#tablename = "";
	#useCache = false;
	#tabledefinition = {};
	#rows = {};
	#config = {}

	#templates = {};

	/**
	 * Initialite the table on the basis of the definition within the
	 * configuration and the list as was loaded from the server.
	 */
	constructor(tablename, useCache, config) {
		this.#tablename = tablename;
		this.#useCache = useCache;
		this.#config = config
		// read the definition / configuration
		this.#tabledefinition = this.#config.get_by_path(".appTables." + tablename);
		// expand common columns placeholder and read the data representation templates
		let tableNodes = this.#tabledefinition.get_children();
		for (let nodeName in tableNodes) {
			if (nodeName.startsWith("_")) {
				let node = tableNodes[nodeName];
				let columnsToAdd = this.#config.get_by_path(".appTables." + nodeName);
				columnsToAdd.copy_children(this.#tabledefinition, false);
				this.#tabledefinition.remove_branch(nodeName);
			}
		}
		// load the data, if cached
		if (this.#useCache) {
			this.#rows = {};
			let csv = window.localStorage.getItem("table." + tablename + ".data");
			this.mergeCsv(csv, false);
		}
	}
	
	/**
	 * Read from csv-string into memory. Merges into existing rows and refreshes
	 * the indices. Set storeUpdate to store the result, if #useCache will
	 * allow.
	 */
	mergeCsv(csvData, storeUpdate) {
		// Split csv data into string arrays
		let rows = TfyhToolbox.csvToObject(csvData);
		// parse rows and insert into table
		for (row of rows) {
			if (!row.uid) {
				alert("Record without uid. ");
				_stopDirty_; // This will cause an error and thus abort.
			}
			// create new or overwrite existing
			let tableRow = (this.#rows[row.uid]) ? this.#rows[row.uid] : {};
			for (field in row) {
				if (this.#tabledefinition.hasChild(field)) {
					// Parse the value and then assign it to a field
					let desc = this.#tabledefinition.getChild(field);
					let strVal = row[field];
					let val = TfyhData.parse(strVal, desc.value_type);
					tablerow[field] = val;
				} 
			}
			// add the row to the table, or replace it
			this.#rows[row.uid] = tableRow;
		}
		// refresh the indices
		this.#refreshIndices();
		// Store the result into the cache.
		if (storeUpdate) {
			if (this.#useCache) {
				let csv = this.#writeCsv(); 
				window.localStroage.setItem("table." + tablename + ".data", csv);
			}
		}
	}
	
	/**
	 * write from memory to csv-string
	 */
	#writeCsv () {
		let csv = "";
		for (childname in this.#tabledefinition.getChildren()) 
			csv += childname;
		csv = substring(csv, 0, csv.length - 1) + "\n";
		for (row of this.#rows) {
			for (field in this.#tabledefinition.getChildren()) {
				// get the data type
				let desc = this.#tabledefinition.getChild(field);
				// format the value
				let strVal = TfyhData.format(row[field], desc.value_type, "csv");
				// encode into csv
				csv += TfyhToolbox.encodeCsvEntry(strVal) + ";";
			}
			csv = substring(csv, 0, csv.length - 1) + "\n";
		}
		return csv;
	}
	
	/**
	 * refresh all indices
	 */
	#refreshIndices() {
		// TODO
	}
	
}