# tqc-php

Command line interface written in PHP for the TinyQueries&trade; Compiler

## Installation

Windows:
- Make sure you have PHP v7.0 or higher
- Download the files `bin/tqc.phar` and `bin/tqc.bat` from this repo and put them in a folder which is in your PATH

Mac/Linux:
- Make sure you have PHP v7.0 or higher
- Run these commands:
	- `wget https://github.com/querytechnology/tqc-php/raw/master/bin/tqc.phar`
	- `mv tqc.phar /usr/local/bin/tqc`
	- `chmod +x /usr/local/bin/tqc`

## Usage

- Make sure you have an API key for the TinyQueries&trade; Compiler. You can get one here: https://tinyqueries.com/signup
- Create a file `.env` in your project folder if you don't have one yet and add your API key for the compiler:
```
TINYQUERIES_API_KEY=<yourkey>
```
- Put your TinyQueries source queries in a folder inside your project folder
- Create a config file `tinyqueries.json` or `tinyqueries.yaml` in your project folder. For example:
```json
{
	"project": {
		"label": "my-project-label"
	},
	"compiler": {
		"dialect": "mysql",
		"input": "<input folder>",
		"output": "<output folder>"
	}
}
```
- `input` should point to the folder of your TinyQueries source queries
- `output` should be a folder in which the output files of the compiler will be put
- To compile your queries run `tqc` from your project folder

For a more detailed description of config files please check https://compile.tinyqueries.com

