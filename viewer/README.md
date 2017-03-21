Viewer
==========
Viewer is a web application template which can show the converted XHTML by 'pdfanalyzer' with its original PDF.

### Usage
1. Copy 'viewer/' directory recursively on the web server.

```$ cp -r viewer /var/www/html/foo```

2. Copy (or make a symbolic link to) the 'xhtml_dir' under the directory.

```$ cp -r ../pdfanalyzer/xhtml /var/www/html/foo/```

3. Open the directory by web browsers.

```http://your.web.server/foo/```

4. You can specify the default pdf by its code (basename).

```http://your.web.server/foo/?code=W12-1010```
