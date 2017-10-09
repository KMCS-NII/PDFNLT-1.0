# 前処理

## 実装済み前処理(2017/10/9現在)

* POS tagging(篠田)

## 手順

### 0. NLTK

nltkのpackageを入れていない場合は、python shellに入って、

```
$ python
>>> import nltk
>>> nltk.download()
```

とするとNLTK Downloaderが起動するので、

```
Downloader> d     # dを押してEnter
Download which package (l=list; x=cancel)?
  Identifier> punkt
Download which package (l=list; x=cancel)?
  Identifier> averaged_perceptron_tagger
Download which package (l=list; x=cancel)?
  Identifier> universal_tagset
Downloader> q   # qで終了
```

のようにする必要があります。

### 1. 前処理

/PDFNLT/preprocess/で、以下のようにスクリプトを走らせます。

```
python preprocess.py
```

#### Tag対応表

tagset=universalを指定した場合のタグ

|Tag|Meaning|English Examples|
|----|-----|-----------|
|ADJ|adjective|new, good, high, special, big, local|
|ADP|adposition|on, of, at, with, by, into, under|
|ADV|adverb|really, already, still, early, now|
|CONJ|conjunction|and, or, but, if, while, although|
|DET|determiner, article|the, a, some, most, every, no, which|
|NOUN|noun|year, home, costs, time, Africa|
|NUM|numeral|twenty-four, fourth, 1991, 14:24|
|PRT|particle|at, on, out, over per, that, up, with|
|PRON|pronoun|he, their, her, its, my, I, us|
|VERB|verb|is, say, told, given, playing, would|
|.|punctuation marks|. , ; !|
|X|other|ersatz, esprit, dunno, gr8, univeristy|

参考:[5. Categorizing and Tagging Words](http://www.nltk.org/book/ch05.html)


## 備考
英文の前処理をするのはこれが初めてなので、何か足りない点などあればご指摘いただければ幸いです。

よろしくお願いいたします。（篠田）