# ActiveRecord tests

## Execute tests

```shell
kahlan
```

## Coverage

```shell
mkdir .nyc_output
XDEBUG_MODE=coverage kahlan --coverage --istanbul=.nyc_output/coverage.json
nyc report --reporter=html --extension=".php"
open coverage/index.html
```
