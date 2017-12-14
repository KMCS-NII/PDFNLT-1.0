The idea of `postprocess.sh` is to run all scripts that need to run after a file in `train` directory was changed.

The script assumes the default directory layout of the data directory.

`textualize.rb` can be run under MRI Ruby or JRuby; it requires the gems `ffi-aspell` and `nokogiri`.

`sentence_splitter.rb` requires JRuby, and the presence of `stanford-corenlp-X.X.X.jar` library (or a link to it) in this directory.
It also requires the `nokogiri` gem.

