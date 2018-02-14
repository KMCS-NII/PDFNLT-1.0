#/bin/bash

script=$(cd $(dirname $0) && pwd)

usage() {
  echo -e "Usage: $0 [-f] [-i] [-v] [-o outdir] <xhtml_dir>"
  echo -e "   or  $0 [-f] [-i] [-v] [-o outdir] <xhtml_file...>"
}

unset inplace
unset force
unset outdir

while getopts "fo:vi" o; do
  case "${o}" in
    f)
      force=1
      ;;
    o)
      outdir="$(cd "${OPTARG}" && pwd)"
      ;;
    i)
      inplace=-i
      ;;
    v)
      verbose=-v
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done
shift $((OPTIND-1))

if [[ -z "$1" ]]
then
  usage
  exit 1
fi


xhtmls=()
shopt -s nullglob

if [ -d "$1" -a -n "$force" ]
then
  # Whole directory, forced
  dir="$(cd $(dirname "$1") && pwd)"
  outdir="${outdir:-$dir/text}"
  pdfs=(--all)
  tsvs=(--all)
else
  if [ -f "$1" ]
  then
    # Individual files
    dir=$(cd $(dirname "$1")/.. && pwd)
    files=("$@")
  else
    # Whole directory, non-forced
    dir="$(cd $(dirname "$1") && pwd)"
    files=("$dir"/pdf/*.pdf)
  fi
  outdir="${outdir:-$dir/text}"

  pdfs=()
  tsvs=()
  for i in "${files[@]}"
  do
    # If not forced, then only pick the files that are not up-to-date
    file="$(basename "$i" .xhtml)"
    file="${file%.pdf}"
    if [ -n "$force" -o ! -f "$dir/xhtml/$file.xhtml" -o "$dir/train/$file.csv" -nt "$dir/xhtml/$file.xhtml" ]
    then
      pdfs+=("$dir/pdf/$file.pdf")
      tsvs+=("$file.csv")
      xhtmls+=("$dir/xhtml/$file.xhtml")
    fi
  done
fi

if [ ${#pdfs[@]} -eq 0 ]
then
  # Everything is up-to-date, nothing to do
  exit
fi

cd "$dir"
mkdir -p "$outdir"

if [ -f paper.model ]
then
  if [ -d train ]
  then
    # Model exists; update the xhtml files
    php "$script/../pdfanalyzer/pdfanalyze.php" -c update_xhtml --with-image --with-wordtag "${tsvs[@]}"
  fi
elif [ -d pdf ]
then
  # Model does not exist; update the model
  php "$script/../pdfanalyzer/pdfanalyze.php" -c update_model "${pdfs[@]}"

  # Generate the xhtml files
  php "$script/../pdfanalyzer/pdfanalyze.php" -c generate_xhtml --with-image --with-wordtag "${pdfs[@]}"
fi

if [ ${#xhtmls[@]} -eq 0 ]
then
  xhtmls=("$dir"/xhtml/*.xhtml)
fi

# Extract text, references; identify words
jruby -J-Xmx256g "$script/textualize.rb" ${inplace} ${verbose} -o "$outdir" "${xhtmls[@]}"

# Extract area information
jruby -J-Xmx256g "$script/iconifier.rb" ${verbose} -o "${outdir}" "${xhtmls[@]}"
