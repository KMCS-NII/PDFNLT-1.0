# Install PDFAnalyzer on Ubuntu 20.04.4 LTS

2022-03-11 sagara@info-proto.com

This document describes the steps taken to build an environment
to run pdfanalyzer on Ubuntu 20 + PHP 7 in March 2022.

## Install CXX (if not yet)

```
$ sudo apt install build-essential
```

## Clone PDFNLT-1.0

```
$ git clone https://github.com/KMCS-NII/PDFNLT-1.0.git
```

## Complie extended poppler

In order to get font information when running xpdftotext,
the extended poppler must be compiled.

To avoid overwriting the distribution's poppler,
set prefix to anywhere you like except "/usr".
(In the following steps, $HOME is specified)

```
$ export PATH=$HOME/bin:$PATH
$ export LD_LIBRARY_PATH=$HOME/lib
$ cd PDFNLT-1.0/pdfanalyzer
$ sudo apt install libfontconfig1-dev libjpeg-dev libopenjp2-7-dev xfonts-scalable
$ wget https://poppler.freedesktop.org/poppler-0.52.0.tar.xz
$ tar xJf poppler-0.52.0.tar.xz
$ cd poppler-0.52.0/
$ gzip -dc ../dist/poppler-0.52.0.diff.gz | patch -p1
$ ./configure --prefix=$HOME --enable-xpdf-headers
$ make
$ make install
$ pdftotext -v
pdftotext version 0.52.0
Copyright 2005-2017 The Poppler Developers - http://poppler.freedesktop.org
Copyright 1996-2011 Glyph & Cog, LLC
$ cd ../
(You may remove "poppler-0.52.0" directory)
```

## Compile poppler and pdffigures

Compile pdffigures using poppler installed above.

Since the distribution's leptonica is too new and causes errors
when compiling pdffigures, download ver. 1.78.0 from GitHub and
compile, install it.

```
$ wget https://github.com/DanBloomberg/leptonica/releases/download/1.78.0/leptonica-1.78.0.tar.gz
$ tar xfz leptonica-1.78.0.tar.gz
$ cd leptonica-1.78.0/
$ ./configure --prefix=$HOME
$ make
$ make install
$ cd ../
(You may remove "leptonica-1.78.0" directory)
```

Install pdffigures.

```
$ sudo apt install unzip
$ unzip dist/pdffigures-20160622.zip
$ cd pdffigures-master
$ PKG_CONFIG_PATH=$HOME/lib/pkgconfig CPLUS_INCLUDE_PATH=$HOME/include make DEBUG=0
$ cp pdffigures $HOME/bin/
$ cd ..
(You may remove "pdffigures-master" directory)
```

## Install crfsuite

```
$ sudo apt install liblbfgs-dev
$ wget https://github.com/downloads/chokkan/crfsuite/crfsuite-0.12.tar.gz
$ tar xfz crfsuite-0.12.tar.gz
$ cd crfsuite-0.12
$ ./configure --prefix=$HOME
$ make
$ make install
$ cd ..
(You may remove "crfsuite-0.12" directory)
```

## Prepare pdfanalyzer

To execute pdfanalyzer, install PHP and extensions.

Train the model using sample PDFs and annotated data.

```
$ sudo apt install aspell aspell-en imagemagick
$ sudo apt install php php-mbstring php-xml php-pspell php-phpdbg
$ tar xfz dist/sampledata.tgz
$ mv sampledata/* .
$ php pdfanalyze.php --command update_training --all
(Please igunore errors from leptonica such as 'Error in boxContains...')
$ php pdfanalyze.php --command update_model
```

## Convert PDF to XHTML

Convert PDFs to XHTMLs using the model file.

```
$ php pdfanalyze.php --command generate_xhtml --all --with-image --with-wordtag
```

