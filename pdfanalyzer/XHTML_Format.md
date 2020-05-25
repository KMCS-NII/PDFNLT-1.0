PDFNLT XHTML Format
========================

## The `Head` part

The `Head` part contains information on the entire document
such as document size and font information.

#### `meta`

```html
<meta docid="C02-1045" />
<meta name="generator" content="pdfanalyzer ver.1.0.20181217" />
<meta name="revised" content="2018-12-17" />
```

The `docid` attribute represents the ID of the document,
which is created from the original file name.

The `generator` attribute represents the version of the `Pdfanalyzer`
which is used to generate this document.

The `revised` attribute represents the date when this document is generated.

#### `pages > page`

```html
<page data-bdr="0.11765,0.03941,0.88263,0.84896" height="11.0000 in" width="8.5000 in" />
```

Information on the size of the page and the area of data contained in it.
This element is generated for each pages.

The `width` attribute is the page width, and the `height` attribute is the page height.
The `data-bdr` attribute holds the left end X coordinate,
the top end Y coordinate, the right end X coordinate,
and the bottom Y coordinate of the area where the content in the page exists.
Coordinate units are proportional to the page width and height.

#### `ftypes > fontspec`

```html
<fontspec id="1" name="CMBX12" size="14.3 pt" />
```

Information on fonts included in this document.
This element is generated fo each fonts in the document.
Fonts with different font sizes are treated as different fonts.

The `id` attribute is the font id, the `name` attribute is the font name,
and the `size` attribute is the font size.

## The `Body` part

The `Body` part manages the body text as a hierarchical structure.

- The 1st level corresponding to the section: `div.section`
- The 2nd level corresponding to the geometrical box: `div.box`
- The 3rd level corresponding to the paragraph: `p`
- The 4th level corresponding to the word or other element: `span.word`, `span.image`, `span-alt-image`

#### `div.section`

```html
<div id="sec-4" class="section" data-name="Section">
```

This element represents a section.
Section numbers are sequentially assigned to the `id` attribute
from `sec-0` to `sec-1`, `sec-2`, ....

The section title is included in the `data-name` attribute.

#### `div.box`

```html
<div id="box-4-0" class="box" data-name="SectionHeader">
```

This element represents a contiguous area within a section.
If there is a page break or a column change, it becomes the next box
even in the middle of the sentence.

Box numbers are sequentially assigned to the `id` attribute
from `box-x-0` to `box-x-1`, `box-x-2`, ..., where `x` is the section number.

For example, the `id` of the first box of section `sec-1` is `box-1-0`.
The `id` of the section is `sec-12` when it is containing the box whose id is `box-12-1` .

#### `p`

```html
<p id="p-4-0-0" data-bdr="0.51471,0.21801,0.77520,0.23148" data-page="1" data-text="3 The Clustering Method">
```

This element reprelents a paragraph.
Paragraph numbers are sequentially assigned to the `id` attribute
from `p-x-y-0` to `p-x-y-1`, `p-x-y-2`, ..., where `x` is the section number
and `y` is the box number.

For example, the `id` of the first paragraph of box `box-1-2` is `p-1-2-0`.
The `id` of the box is `box-2-1` and the `id` of the section is `sec-2`
when they are containing the paragraph whose id is `p-2-1-2` .

The `data-text` attribute contains the text of the paragraph.
The `data-page` attribute contains the page number (starting from 0),
and the `data-bdr` attribute contains the area coordinates of that paragraph.
The coordinates of the area are represented by the left end X coordinate,
the top end Y coordinate, the right end X coordinate, and the bottom Y coordinate.
Coordinate units are proportional to page width and height.

If there is a page break in the middle of a paragraph and it has been separated,
the id of the paragraph to be continued is set in the `data-continue-to` attribute.
Conversely, if the paragraph is a continuation of another paragraph,
the `data-continued-from` attribute is set to the id of the previous paragraph.
The `data-continue-to` and `data-continued-from` attributes are omitted if it is not necessary.

#### `span.word`

```html
<span id="w-4-0-0-0" class="word" data-bdr="0.51471,0.21801,0.52570,0.23148" data-ftype="2" data-space="bol">3</span>
```

This element represents a word. It is created when XHTML is generated with "--with-wordtag" option turned on.

Word numbers are sequentially assigned to the `id` attribute
from `w-x-y-z-0` to `w-x-y-z-1`, `w-x-y-z-2`, ..., where `x` is the section number,
`y` is the box number and `z` is the paragraph number.

For example, the `id` of the forst word of paragraph `p-2-4-0` is `w-2-4-0-0`.
The `id` of the paragraph is `p-13-2-0`, the `id` of the box is `box-13-2`
and the `id` of the section is `sec-13` when they are containing the word
whose id is `w-13-2-0-4`.

The `data-bdr` attribute contains the area coordinates of that word.
The coordinates of the area are represented by the left end X coordinate,
the top end Y coordinate, the right end X coordinate, and the bottom Y coordinate.
Coordinate units are proportional to page width and height.

The `data-ftype` attribute contains the font id.

The `data-space` attribute indicates whether there is a space before the word.
In case of `bol` it is at the beginning of the line.
In case of `space` there is a space before the word, and `nospace` has no whitespace.

If a line break occurs in the middle of a word, the `data-fullform`,
`data-originalform`, and `data-refid` attributes will be added.
In `data-fullform` attribute, the complete word string are stored, which are combined by removing the line break.
The original substring is included in `data-originalform` attribute.
The `data-refid` attribute indicates the first word ID of the divided word.

```html
<span id="w-1-1-0-6" class="word" data-bdr="0.42780,0.23433,0.48538,0.24669" data-ftype="4" data-fullform="clustering" data-originalform="cluster-" data-refid="w-1-1-0-6" data-space="space">clustering</span>
<span id="w-1-1-0-7" class="word" data-bdr="0.11765,0.24999,0.14143,0.26234" data-ftype="4" data-fullform="clustering" data-originalform="ing" data-refid="w-1-1-0-6" data-space="bol" />
```

If the `class` attribute contains `alt-text`, that word is an alternate text for the image
contained in the `span.alt-image` element immediately following.
This word and image duplicate contents, therefore, it is necessary to display only one of them
for smooth reading.

#### `span.image`

```html
<span id="w-2-2-0-0" class="image" data-bdr="0.51294,0.21091,0.89882,0.39727">
<img src="images/C02-1045/C02-1045-p-2-2-0.png" data-hocr="images/C02-1045/C02-1045-w-2-2-0-0" data-ocr-dpi="400" />
</span>
```

This element represents a image. It is created when XHTML is generated with "--with-image" option turned on.

The `id` attribute is numbered in the same format as word `w-x-y-z-0`.
To determine whether it is a word or an image, use the `class` attribute.

The `data-bdr` attribute contains the area coordinates of that image.
The coordinates of the area are represented by the left end X coordinate,
the top end Y coordinate, the right end X coordinate, and the bottom Y coordinate.
Coordinate units are proportional to page width and height.

When the [Tesseract OCR](https://github.com/tesseract-ocr/) is installed,
the image will be OCR processed. In that case, `data-hocr` and `data-ocr-dpi` attributes will be added to the `img` element inside the `span.image`.
The `data-hocr` attribute contains the relative path to the file which is containing
OCR results in the hOCR format.
The `data-ocr-dpi` attribute indicates the image resolution used to the OCR processing.


#### `span.alt-image`

```html
<span id="w-5-2-0-13" class="alt-image" data-bdr="0.64673,0.40176,0.88226,0.41683">
<img src="images/C02-1045/C02-1045-p-5-2-0.png" />
</span>
```

This element represents a alternative image. It is created when XHTML is generated with "--with-alter-image" option turned on.

The attributes of this element are equal to the `span.image` element.
