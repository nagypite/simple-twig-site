# simple-twig-site
Simple quasi-static website engine with Twig templating

## Setup

Install dependencies:
```bash
$ composer install
```

Set up cache directory for twig:

```bash
$ mkdir cache
$ chmod 777 cache
```

## Usage

You can use `docker compose up` to run the development server.

You can also run the command `./compile-scss.php [-w]` to compile SCSS into CSS. The `-w` switch will watch for changes and re-compile.
