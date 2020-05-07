# tqc-php

Command line interface written in PHP for the TinyQueries&trade; Compiler

## Installation

Windows:
- Make sure you have PHP v7.0 or higher
- Download the files `bin/tqc.phar` and `bin/tqc.bat` from this repo and put them in a folder which is in your PATH

Mac/Linux:
- Make sure you have PHP v7.0 or higher
- Run these commands:
```
wget https://github.com/querytechnology/tqc-php/raw/master/bin/tqc.phar
sudo mv tqc.phar /usr/local/bin/tqc
sudo chmod +x /usr/local/bin/tqc
```

## Setup TinyQueries&trade; for your project

It is assumed you have a folder for your project. It can be an empty folder or a folder which contains other code as well.
- Make sure you have an API key for the TinyQueries&trade; Compiler. You can get one here: https://tinyqueries.com/signup
- Either add `TINYQUERIES_API_KEY` to your ENV variabeles OR create a file `.env` in the root of your project folder if you don't have one yet and add your API key:
```
TINYQUERIES_API_KEY=<yourkey>
```
- Create a folder inside your project folder (for example `tinyqueries`) in which you put your TinyQueries source queries
- Create a folder inside your project folder (for example `sql`) in which you want the compiler to put your compiled queries
- Create a config file `tinyqueries.json` or `tinyqueries.yaml` in the root of your project folder. For example:
```yaml
project:
  label: my-project-label
compiler:
  dialect: mysql
  input: ./tinyqueries
  output: ./sql
```

For a more detailed description of config files please check https://compile.tinyqueries.com

## Compile your queries

Once you have setup your project you just have to execute
```
tqc
```
from your project folder each time you want to compile your source files
