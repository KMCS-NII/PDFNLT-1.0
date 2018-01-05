# postprocess.sh

The idea of `postprocess.sh` is to run all scripts that need to run after a file in `train` directory was changed.

The script assumes the default directory layout of the data directory.

`textualize.rb` can be run under MRI Ruby or JRuby; it requires the gems `ffi-aspell` and `nokogiri`.

`sentence_splitter.rb` requires JRuby, and the presence of `stanford-corenlp-X.X.X.jar` library (or a link to it) in this directory.
It also requires the `nokogiri` gem.


## RVM
---
The easiest way to install JRuby is using RVM (Ruby Version Manager). The usual installation is local (for a single user), but to allow the web server to use it, it is easiest if RVM is installed globally.
The up-to-date command to install RVM globally can be found at https://rvm.io/rvm/install. At the time of writing this, global (multi-user) RVM is installed using:

```
# Install RVM
gpg --keyserver hkp://keys.gnupg.net --recv-keys 409B6B1796C275462A1703113804BB82D39DC0E3 7D2BAF1CF37B13E2069D6956105BD0E739499BDB
curl -sSL https://get.rvm.io | sudo bash -s stable

# Install newest JRuby
rvm install jruby

# Temporarily use the JRuby in the current session
rvm use jruby

# Install needed gems
gem install ffi-aspell nokogiri
```

A command can be executed in the environment where JRuby is the current Ruby as follows:

```
rvm jruby do ./postprocess.sh xhtml/
```
