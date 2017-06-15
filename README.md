# plugin-bigcommerce-prc

A ekomi Product Review Container for bigCommerce
***
The eKomi product review container allows an easy integration of eKomi Reviews and ratings into your webshop. It allows you individual positioning of product reviews and includes the Google rich snippet functionality.
Before installing and activating your plugin, please contact support@ekomi.de, this is necessary to ensure everything has been set up correctly and activated from eKomi’s side.

**Key features of Product Review Container:**

+ Product total reviews
+ Product avg reviews (star rating)
+ List of reviews with pagination and sorting options
+ Rating schema for google structured data
+ Mini star ratings
+ The parent /child review display

## Getting Started

1. Clone the project in local. 
2. Create database named "bigcommerce_prc"

After creating the database, import bigcommerce_prc.sql in created database.

Set the database user and password in *.env* file.

### Prerequisites

Composer needs to install to run the setup in local.

```
sudo apt-get update
sudo apt-get install curl php5-cli

curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### Installing

Follow these steps to install the plugin.

```
git clone https://github.com/ekomi-ltd/plugin-bigcommerce-prc.git
```

* Clone the project in local. 
* Create database named "bigcommerce_prc"

After creating the database, import *bigcommerce_prc.sql* in created database.

Set the database user and password in *.env* file.

Update the composer in plugin plugin-bigcommerce-prc directory.

```
composer update
```

## Deployment

Steps to deploy this on a live system.
* Upload the plugin Live server
* Create database named "bigcommerce_prc" on live
* Import database bigcommerce_prc.sql
* Run composer update
* Update .env to set plugin url and database credentials

## Built With

* silex framework
* Twig templating engine
* symfony/twig-bridge
* Doctrine
* bigCommerce Api

## Versioning

### v1.0.0 (15-06-2017)

- A complete working plugin

## Authors

* **Khadim raath** - *khadim.nu@gmail.com* - [github profile](https://github.com/kRaath)

See also the list of [contributors](https://github.com/ekomi-ltd/plugin-bigcommerce-official/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
