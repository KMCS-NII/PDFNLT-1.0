#!/usr/bin/env ruby

# encoding: utf-8

require 'nokogiri'
require 'optparse'
require 'csv'
require 'ffi/aspell'

require 'pp'
require 'pry'

# Requirements:
#
# Ruby 2.x
# gem install nokogiri ffi-aspell
#
# aspell package with appropriate languages
# e.g. OS X:   brew install aspell --with-lang-en --with-lang-de
#      Debian: apt-get install aspell aspell-en aspell-de
#
#
# XXX: Had problems with locale.
#
#     export LC_ALL=en_US.UTF-8

class TextExtractor
  CANCEL_BREAK_BOXES = %w(Abstract Body)
  ALWAYS_BREAK_BOXES = %w(Equation)

  NO_SPACE_LANG = /^ja|km|ko|lo|th|zh/
  LIGATURES = {
    "\ufb00" => "ff",
    "\ufb01" => "fi",
    "\ufb02" => "fl",
    "\ufb03" => "ffi",
    "\ufb04" => "ffl",
    "\ufb05" => "st",
    "\ufb06" => "st",
  }
  LIGATURE_RE = /[#{LIGATURES.keys.join}]/

  def initialize(xhtml_filename, lang)
    @xhtml_filename = xhtml_filename
    @no_space_lang = lang =~ NO_SPACE_LANG
    @document = File.open(xhtml_filename, encoding: 'UTF-8') { |f|
      Nokogiri::XML(f, nil, 'UTF-8') { |config| config.strict.nonet }
    }
    @speller = FFI::Aspell::Speller.new(lang)
    @output = ""
    @compound = nil
    @figures = Hash.new { |h, k| h[k] = [] }
    @footnotes = Hash.new { |h, k| h[k] = [] }
    @references = []
    @needs_space = false
    @needs_para = false
    @needs_section = false

    @document.css('body > div.section').each do |section|
      if @needs_section
        end_para
        @output += "\n\n\n\n"
      end
      @needs_para = false
      @needs_space = false

      sect_id = section['id']

      section.css('> div.box').each do |box|
        box.css('> p').each do |para|
          if para['data-fig']
            @figures[sect_id] << para
          elsif box['data-name'] == 'Footnote'
            @footnotes[sect_id] << para
          else
            process_para(para, box['data-name'])
          end
        end
      end
      
      @needs_section = true
    end

    add_compound

    process_extra(@footnotes)
    process_extra(@figures)

    end_para
  end

  def mark_length(xml_word, text)
    xml_word['data-from'] = @output.length
    @output += text
    xml_word['data-to'] = @output.length
  end

  def add_compound
    if @compound
      mark_length(@last_word, @compound)
      @compound = nil
    end
  end

  def process_extra(type)
    type.each do |section, paras|
      end_para
      @output += "\n\n\n\n"
      @needs_para = false
      @needs_space = false

      paras.each do |para|
        process_para(para)
      end

      add_compound
    end
    @output
  end

  def process_para(para, box_name=nil)
    if @needs_section && !@needs_para && ALWAYS_BREAK_BOXES.include?(box_name)
      @needs_para = true
    end

    if @needs_para
      end_para
      @output += "\n\n"
      @needs_para = false
      @needs_space = false
    end
    start_para(para, box_name)

    para.css('> span.word').each do |word|
      next if word.text.empty?

      wstr = word.text.strip.gsub(LIGATURE_RE, LIGATURES)

      if @needs_space
        @output += ' '
        @needs_space = false
      end
      
      if wstr[-1] == '-' && !@no_space_lang
        @compound = wstr
        @last_word = word
      else
        if @compound
          nohyp = @compound[0...-1]
          nohyp_spell = (nohyp + wstr).gsub(/[[:punct:]]+$|^[[:punct:]]/, '')
          if @speller.correct?(nohyp_spell)
            mark_length(@last_word, nohyp)
          else
            mark_length(@last_word, @compound)
          end
          @compound = nil
        end
        mark_length(word, wstr)

        @needs_space = !@no_space_lang
        @compound = nil
      end
    end

    @needs_para =
      !box_name || !CANCEL_BREAK_BOXES.include?(box_name) || ".!?".include?(@output[-1])
  end

  def start_para(para=nil, box_name=nil)
    unless @para_data
      @para_data = {
        start: @output.length,
        box_name: box_name,
        id: para['id']
      }
    end
  end

  def end_para
    return unless @para_data
    if @para_data[:box_name] == 'Reference'
      @ref_id = (@ref_id || 0) + 1
      @references << [
        @ref_id,
        @para_data[:id],
        @output[@para_data[:start]..-1]
      ]
    end
    @para_data = nil
  end

  def to_s
    @output
  end

  def to_xml
    @document.to_xml
  end

  def to_map
    words = []
    @document.css('span.word').each do |word|
      words << [word['id'], word['data-from'].to_i, word['data-to'].to_i]
    end
    words.sort_by! { |word| word[1..-1] }

    CSV.generate(col_sep: "\t") do |csv|
      csv << ["ID", "From", "To", @xhtml_filename]
      words.each do |word|
        csv << word
      end
    end
  end

  def to_ref
    CSV.generate(col_sep: "\t") do |csv|
      csv << ["#", "ID", "Text", @xhtml_filename]
      @references.each do |no, id, text|
        id = @xhtml_filename[/([^\/]*)\.xhtml$/, 1] + "-" + id[/p(?:aragraph)?-(.*)/, 1]
        csv << [no, id, text]
      end
    end
  end

  def scrub_map
    @document.css('span.word').remove_attr('data-from').remove_attr('data-to')
  end
end

def output(fname, base, ext)
  case fname
  when "-"
    puts yield
  when nil
    # skip
  else
    fname = File.join(fname, "#{base}.#{ext}") if File.directory?(fname)
    File.write(fname, yield, encoding: 'UTF-8')
  end
end

if __FILE__ == $0
  options = {
    lang: "en"
  }
  OptionParser.new do |opts|
    opts.banner = "Usage: #{__FILE__} [options] <file.xhtml...>"

    opts.on("-l", "--language LANG", "Text language - requires aspell dict [en]") do |lang|
      options[:lang] = lang
    end
    opts.on("-x", "--xhtml FILE", "XHTML output file") do |filename|
      options[:xhtml_filename] = filename
    end
    opts.on("-t", "--text FILE", "Plain text output file") do |filename|
      options[:text_filename] = filename
    end
    opts.on("-m", "--map FILE", "Word ID mapping TSV file") do |filename|
      options[:map_filename] = filename
    end
    opts.on("-r", "--references FILE", "Reference TSV file") do |filename|
      options[:ref_filename] = filename
    end
    opts.on("-M", "--[no-]xhtml-map", "Insert positions into XHTML") do |insert|
      options[:xhtml_map] = insert
    end
    opts.on("-i", "--[no-]in-place", "Replace the XHTML file") do |inplace|
      options[:inplace] = inplace
    end
      opts.on("-v", "--[no-]verbose", "Report file names") do |value|
        options[:verbose] = value
      end
    opts.on("-o", "--output FILE", "Sets -x, -t, -m, -r") do |filename|
      options[:xhtml_filename] = filename
      options[:text_filename] = filename
      options[:map_filename] = filename
      options[:ref_filename] = filename
    end
    opts.on_tail("-h", "--help", "Show this message") do
      puts opts
      puts <<-EOF
          FILE can be a filename, a directory (file will be XHTML file's name
          with a new extension), or `-` (standard output).
      EOF
      exit
    end
  end.parse!

  ARGV.each do |source_filename|
    options[:xhtml_filename] = source_filename if options[:inplace]

    te = TextExtractor.new(source_filename, options[:lang])
    base = File.basename(source_filename, '.xhtml')

    output(options[:map_filename], base, "map") { te.to_map }
    te.scrub_map unless options[:xhtml_map]
    output(options[:xhtml_filename], base, "xhtml") { te.to_xml }
    output(options[:ref_filename], base, "ref") { te.to_ref }
    output(options[:text_filename], base, "txt") { te.to_s }
  end
end
