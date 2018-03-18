# CSV Filter

The CSV filter is a command line application written in PHP to help processing large CSV files. This tool provides
ways to include or exclude data from the source file. A new .csv file is created and contains the records that match the
given criteria.


## Requirements
**Install PHP 7.1 or greater**  You can verify your PHP version by executing `php -v`. 

**Install composer** 

```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

**Run composer install**
```
composer install
```


## Usage Examples
```
# Will match all horloges from guess
bin/console csv:filter --in=testfiles/products.csv --out=testfiles/out2.csv --search="horloges guess"

# Will match only horloges
bin/console csv:filter --in=testfiles/products.csv --out=testfiles/out2.csv --search="horloges guess" --filter="vrouw --filter="dame"
```