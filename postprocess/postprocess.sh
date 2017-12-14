#/bin/bash

if [[ "$1" == "-f" ]]
then
  force=1
  shift
fi

if [[ -z "$1" ]]
then
  echo -e "Usage: $0 [-f] <xhtml_dir>"
  echo -e "   or  $0 [-f] <xhtml_file...>"
  exit -1
fi

script=$(cd $(dirname $0) && pwd)

xhtmls=()
shopt -s nullglob

if [ -f "$1" -o -z "$force" ]
then
  if [ -f "$1" ]
  then
    # Individual files
    dir=$(cd $(dirname "$1")/.. && pwd)
    files=("$@")
  else
    # Whole directory, non-forced
    dir=$(cd $(dirname "$1") && pwd)
    files=($dir/pdf/*.pdf)
  fi

  pdfs=()
  for i in "${files[@]}"
  do
    # If not forced, then only pick the files that are not up-to-date
    file=$(basename "$i" .xhtml)
    file=${file%.pdf}
    if [ -n "$force" -o ! -f "$dir/xhtml/$file.xhtml" -o "$dir/train/$file.csv" -nt "$dir/xhtml/$file.xhtml" ]
    then
      pdfs+=("$dir/pdf/$file.pdf")
      xhtmls+=("$dir/xhtml/$file.xhtml")
    fi
  done
else
  # Whole directory, forced
  dir=$(cd $(dirname "$1") && pwd)
  pdfs=(--all)
fi

if [ ${#pdfs[@]} -eq 0 ]
then
  # Everything is up-to-date, nothing to do
  exit
fi

cd $dir



# Propagate changes to training files
php $script/../pdfanalyzer/pdfanalyze.php -c update_model

# Generate xhtml
php $script/../pdfanalyzer/pdfanalyze.php -c generate_xhtml --with-image --with-wordtag "${pdfs[@]}"

if [ ${#xhtmls[@]} -eq 0 ]
then
  xhtmls=($dir/xhtml/*.xhtml)
fi

# Extract text, references; identify words
ruby $script/textualize.rb -i -o text -l en "${xhtmls[@]}"

# Extract sentences, math, citations
jruby -J-Xmx256g $script/sentence_splitter.rb -i -o text "${xhtmls[@]}"
