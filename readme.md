# Freshloop-Controlunit

The hub-part of the freshloop-network.

## Getting Started

These instructions will get you the latest version of this project up and running on your local mashine for testing and development purposes. See installation on how to deploy it on a live system.

Please note, that this application is intended to run in a linux environment. Thereby it is recommended to set up a virtual machine for that purpose.

### Prerequesites

* **[PHP](https://www.php.net/releases/7_2_0.php)** - A recent version of PHP, preferably PHP 7.2 or higher
* **Apache Server** - A local Server. Recommended: Express Installation of [Xampp](https://www.apachefriends.org/de/index.html) -> Apache Server.
* **MySQL Instance** - Comes with the [Xampp](https://www.apachefriends.org/de/index.html) installation

### Installation

A short guidance on how to set the project up on you local mashine.

After forfilling the prerequesites mentioned above, the project can be cloned with the following command from your git bash:

```
git clone https://github.com/F9lke/freshloop-controlunit
```

Since there is no auto-installation functionality, it is essential to manually create the table structures. Please copy and paste the code found in the advised file into your mysql bash or phpmyadmin sql code line.

```
root > lib > sql > freshloop.sql
```

In interests of not comitting all libraries, additional ones, that are positioned in the following location, are part of the project. They have to be downloaded and inserted to their intended destination. See built with for a list of the dependencies needed.

```
root > lib > ui > *
```

## Built With

Form: [Name+Link] - [Description] - [Name of Directory in Project]

* [Bootstrap](https://getbootstrap.com/) - The main web framework used for frontend purposes - bootstrap
* [Bulma](https://bulma.io/) - The fallback web framework used for frontend purposes - bulma
* [ChartJS](https://www.chartjs.org/) - A comprehensive and visually stunning chart library - chartjs
* [DriverJS](https://github.com/kamranahmedse/driver.js?files=1) - An interactive tutorial library - driverjs
* [Fontawesome](https://fontawesome.com/?from=io) - The icon supplied used - fontawesome
* [jQuery](https://jquery.com/) - The main JavaScript framework used - jquery
* [Popper](https://popper.js.org/) - The tooltip positioning engine used - popper
* [Slick](https://github.com/kenwheeler/slick/) - An intuitive carousel library - slick
* [Timepicker](https://github.com/sandunangelo/jquery-timesetter) - A jQuery plugin to generate timepicker-ui components - timepicker
* [Toastr](https://github.com/CodeSeven/toastr) - A basic tooltip library - toastr

