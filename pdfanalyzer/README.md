pdfanalyzer
==========
pdfanalyzer is a command line tool that can be used to convert PDF document to XHTML.

### Usage
1. Execute 'pdfanalyze.php' from command line to show help:

```php pdfanalyze.php --help```

2. Convert PDF document to XHTML: 

```php pdfanalze.php --command generate_xhtml /path/to/pdf```

The xhtml file will be generated under the 'xhtml/' directory.

### Dependencies
pdffigures requires [poppler](http://poppler.freedesktop.org/), [pdffigures](http://pdffigures.allenai.org/), [crfsuite](http://www.chokkan.org/software/crfsuite/) installed. PHP 5.3.3 or later (but not 7.x) is also required.

We have tested the installation process on CentOS 6.8.
Please read 'INSTALL' to follow our install procedure.
