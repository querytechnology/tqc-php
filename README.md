# tqc-php
Command line interface in php for TinyQueries compiler

## Example config file

The folder from which the script is run should contain a file `tinyqueries.json`

```json
{
	"project": {
		"label": "my-project-label"
	},
	"compiler": {
		"version": "v3.9.0",
		"apiKey": "1234567890",
		"input": "<input folder>",
		"output": "<output folder>"
	}
}
```

## Optional settings

### compiler.lang

Possible values: `sql` | `php`

### compiler.output

`compiler.output` can be used as a dictionary as well, for example:

```json
{
	"compiler": {
		"output": {
			"sql": "<output folder sql>",
			"php": "<output folder php>"
		}
	}
}
```

### compiler.outputFieldNames

```json
{
    "compiler": {
        "outputFieldNames": "full|last-part-only"
    }
}
```
