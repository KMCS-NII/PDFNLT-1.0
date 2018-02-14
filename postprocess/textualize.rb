#!/usr/bin/env jruby
# encoding: utf-8

require 'pry'

def require_stanford_parser(stanford_parser_jar=nil)
  unless defined?(JRUBY_VERSION)
    STDERR.puts "Error: needs JRuby"
    exit 1
  end

  unless stanford_parser_jar
    script_dir = File.dirname(__FILE__)
    stanford_parser_jar = Dir["#{script_dir}/stanford-corenlp-*.jar"].grep(/stanford-corenlp-\d+.\d+.\d+\.jar$/).first
    unless stanford_parser_jar
      STDERR.puts "Error: cannot find `#{script_dir}/stanford-corenlp-X.X.X.jar`"
      exit 1
    end
  end
  require_relative stanford_parser_jar

  java_import "edu.stanford.nlp.process.PTBTokenizer"
  java_import "edu.stanford.nlp.process.CoreLabelTokenFactory"
  java_import "edu.stanford.nlp.process.WordToSentenceProcessor"
  java_import "java.io.StringReader"
end

require 'nokogiri'
require 'optparse'
require 'csv'


Paragraph = Struct.new(:section_id, :id, :sect_name, :box_name, :words)
Word = Struct.new(:id, :text, :start, :node) do
  def to_s
    text
  end
end
Sentence = Struct.new(:id, :sect_name, :box_name, :text, :words)
CSV_COLS = %i(id sect_name box_name text words)
CSV_COLS_POS = CSV_COLS.map { |field| Sentence.members.index(field) }

def find_sentences(paragraph)
  text = paragraph.words.join
  para_offset = paragraph.words.first.start
  tokenizer = PTBTokenizer.new(StringReader.new(text), CoreLabelTokenFactory.new, "")
  tokens = tokenizer.to_a
  sentence_parses = WordToSentenceProcessor.new.process(tokens)

  word_iter = paragraph.words.each
  word = word_iter.next
  sentences = []
  begin
    sentence_parses.map.with_index do |sentence_parse, index|
      id = paragraph.id.sub(/^p-/, 's-') + "-#{index}"
      b = sentence_parse[0].beginPosition
      e = sentence_parse[-1].endPosition
      sentence = Sentence.new(id, paragraph.sect_name, paragraph.box_name, text[b...e], [])
      b += para_offset
      e += para_offset

      sentences << sentence

      while word && word.start < b
        word = word_iter.next
      end

      while word && word.start < e
        sentence.words << word.id if word.id
        word = word_iter.next
      end
    end
  rescue StopIteration
    # No worries
  end
  sentences
end



# Requirements:
#
# JRuby
# gem install nokogiri
#
#
# XXX: Had problems with locale.
#
#     export LC_ALL=en_US.UTF-8

class TextExtractor
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

  def initialize(xhtml_filename, options)
    @xhtml_filename = xhtml_filename
    @document = File.open(xhtml_filename, encoding: 'UTF-8') { |f|
      Nokogiri::XML(f, nil, 'UTF-8') { |config| config.strict.nonet }
    }

    paragraphs = {}
    paragraph_sequence = []
    last_para = nil
    reference_paragraphs = []
    cite = nil
    math = nil
    maths = []
    word_nodes = {}
    cites = Hash.new { |h, k| h[k] = [] }
    pos = 0

    @document.css('body > div.section').each do |section|
      section_id = section['id']
      section_name = section['data-name']

      section.css('> div.box').each do |box|
        box_name = box['data-name']

        box.css('> p').each do |para|
          math_para = false
          broken = false
          para_id = para['id']
          page_id = para['data-page'].to_i

          # Test if standalone equation should continue from last para
          if box_name == 'Equation' && last_para
            p = paragraphs[last_para]
            if !/[?!.]$/.match(p.words[-1].to_s)
              continued_from_id = last_para
              math_para = true
            end
          else
            continued_from_id = para['data-continued-from']
          end

          unless continued_from_id || paragraph_sequence.empty?
            text = "\n\n"
            paragraph_sequence[-1].words << Word.new(nil, text, pos)
            pos += text.length
            broken = true
          end

          if !continued_from_id && box_name == 'Reference'
            reference_paragraphs << para_id
          end

          nodes = para.children
          if nodes[0]['data-refid']&.!=(nodes[0]['id'])
            nodes.shift
          end

          words = nodes.select { |node|
            !node['data-refid'] || node['id'] == node['data-refid']
          }.flat_map { |node|
            # ignore non-word content
            next if node.text?

            space_val = node['data-space']
            if space_val == 'nospace' || (space_val == "bol" && pos == 0) || broken
              space = nil
              broken = false
            else
              space = Word.new(nil, ' ', pos)
            end
            text = node['data-fullform'] || node.text.gsub(/\s+/, ' ')
            id = node['id']
            word_nodes[id] = node

            case
            when cite
              # inside a citation: skip everything
              math = nil
              space = nil
              word = Word.new(id, '', pos)
              cite = nil if cite == node['id']
            when (cite = node['data-cite-end'])
              # starting a citation: make a dummy word
              cids = node['data-cite-id'].split(',')
              text = cids.map { |cid| "CITE-#{cid}" }.join(', ')
              cids.each do |cid|
                cites[cid] << id
              end
              word = Word.new(id, text, pos)
            when node['data-math'] == 'B-Math' ||
                (node['data-math'] == 'I-Math' && !math) ||
                (math_para && !math)
              # starting an equation
              mid = "MATH-#{math_para ? para_id : id}"
              word = Word.new(id, mid, pos)
              math = [mid, id, id, page_id, *node['data-bdr'].split(',').map(&:to_f)]
              maths << math
            when node['data-math'] == 'I-Math' ||
                math_para
              # inside an equation: skip while calculating bbox
              space = nil
              word = Word.new(id, '', pos)
              math[2] = id
              new = node['data-bdr'].split(',').map(&:to_f)
              math[4] = [math[4], new[0]].min
              math[5] = [math[5], new[1]].min
              math[6] = [math[6], new[2]].max
              math[7] = [math[7], new[3]].max
            else
              math = nil
              text.gsub!(LIGATURE_RE, LIGATURES)
              word = Word.new(id, text, pos, node)
            end

            pos += word.to_s.length
            to_add = if space
              word.start += 1
              pos += 1
              [space, word]
            else
              word
            end

            node['data-from'] = word.start
            node['data-to'] = pos

            to_add
          }.compact

          if continued_from_id
            paragraphs[para_id] = paragraphs[continued_from_id]
            paragraphs[para_id].words += words
            last_para = continued_from_id
          else
            paragraph_sequence << paragraphs[para_id] = Paragraph.new(section_id, para_id, section_name, box_name, words)
            last_para = para_id
          end

          # chain independent math only on these box types:
          last_para = nil unless ['Body'].include?(box_name)
        end
      end
    end

    paragraph_sentence_hash = Hash[paragraph_sequence.map { |paragraph|
      [paragraph.id, find_sentences(paragraph)]
    }]
    sentences = paragraph_sentence_hash.values.flatten

    sentences.each do |sentence|
      sent_id = sentence.id
      sentence.words.each do |word_id|
        word_nodes[word_id]['data-sent-id'] = sent_id
      end
    end

    @sent_tsv = CSV.generate(headers: CSV_COLS, col_sep: "\t") do |csv|
      csv << CSV_COLS
      sentences.each do |sentence|
        sentence.words = sentence.words.join(',')
        csv << sentence.values_at(*CSV_COLS_POS)
      end
    end

    @math_tsv = CSV.generate(col_sep: "\t") do |csv|
      csv << ['MathID', 'StartID', 'EndId', 'Page', 'X1', 'Y1', 'X2', 'Y2']
      maths.each do |row|
        csv << row
      end
    end

    @cite_tsv = CSV.generate(col_sep: "\t") do |csv|
      csv << ['CiteID', 'Text', 'From']
      reference_paragraphs.each do |para_id|
        cid = "CITE-#{para_id}"
        para = paragraphs[para_id]
        text = para.words.join.strip
        csv << [cid, text, cites[para_id].join(',')]
      end
    end

    @text = paragraph_sequence.flat_map { |para| para.words }.join
  end

  attr_reader :sent_tsv, :math_tsv, :cite_tsv

  def to_s
    @text
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

  def scrub_map
    @document.css('span.word').remove_attr('data-from').remove_attr('data-to')
  end
end

def output(fname_opt, ext, xhtml_dir, base, options)
  if (fname = options[fname_opt]) == "-"
    puts yield

  elsif !fname.nil?
    fname = File.join(fname, "#{base}") + "." + ext if File.directory?(fname)
    File.write(fname, yield, encoding: 'UTF-8')

  elsif (dir = options[:base_dir])
    name = options[:base_name]
    dir = xhtml_dir if dir == "-"

    if name
      fname = File.join(dir, name) + "." + ext
    else
      fname = File.join(dir, base) + "." + ext
    end
    File.write(fname, yield, encoding: 'UTF-8')
  end
end

if __FILE__ == $0
  options = {}
  OptionParser.new do |opts|
    opts.banner = "Usage: #{__FILE__} [options] <file.xhtml...>"

    opts.on("-x", "--xhtml FILE", "XHTML output file") do |filename|
      options[:xhtml_filename] = filename
    end
    opts.on("-t", "--text FILE", "Plain text output file") do |filename|
      options[:text_filename] = filename
    end
    opts.on("-w", "--word FILE", "Word ID mapping TSV file") do |filename|
      options[:word_filename] = filename
    end
    opts.on("-r", "--references FILE", "Reference TSV file") do |filename|
      options[:ref_filename] = filename
    end
    opts.on("-s", "--sent FILE", "Sentence TSV file") do |filename|
      options[:sent_filename] = filename
    end
    opts.on("-m", "--math FILE", "Math equation TSV file") do |filename|
      options[:math_filename] = filename
    end
    opts.on("-c", "--cite FILE", "Citation TSV file") do |filename|
      options[:cite_filename] = filename
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
    opts.on("-S", "--stanford-parser FILE", "Path to stanford-corenlp-X.X.X.jar") do |filename|
      options[:stanford_parser_jar] = filename
    end
    opts.on("-o", "--output FILE", "Sets all output options") do |filename|
      if File.directory?(filename)
        options[:base_dir] = filename
      else
        options[:base_dir] = File.dirname(filename)
        options[:base_name] = File.basename(filename, ".xhtml")
      end
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

  require_stanford_parser(options[:stanford_parser_jar])

  ARGV.each do |source_filename|
    puts "textualize: #{source_filename}" if options[:verbose]

    options[:xhtml_filename] = source_filename if options[:inplace]

    te = TextExtractor.new(source_filename, options)
    base = File.basename(source_filename, '.xhtml')
    xhtml_dir = File.dirname(source_filename)

    output(:word_filename, "word.tsv", xhtml_dir, base, options) { te.to_map }
    te.scrub_map unless options[:xhtml_map]
    output(:xhtml_filename, "xhtml", xhtml_dir, base, options) { te.to_xml }
    output(:text_filename, "txt", xhtml_dir, base, options) { te.to_s }
    output(:sent_filename, "sent.tsv", xhtml_dir, base, options) { te.sent_tsv }
    output(:math_filename, "math.tsv", xhtml_dir, base, options) { te.math_tsv }
    output(:cite_filename, "cite.tsv", xhtml_dir, base, options) { te.cite_tsv }
  end
end
