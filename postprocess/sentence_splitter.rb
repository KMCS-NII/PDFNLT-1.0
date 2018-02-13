#!/usr/bin/env jruby

unless defined?(JRUBY_VERSION)
  STDERR.puts "Error: needs JRuby"
  exit 1
end



def require_stanford_parser
  script_dir = File.dirname(__FILE__)
  stanford_parser_jar = Dir["#{script_dir}/stanford-corenlp-*.jar"].grep(/stanford-corenlp-\d+.\d+.\d+\.jar$/).first
  unless stanford_parser_jar
    STDERR.puts "Error: cannot find `#{script_dir}/stanford-corenlp-X.X.X.jar`"
    exit 1
  end
  require_relative stanford_parser_jar

  java_import "edu.stanford.nlp.process.PTBTokenizer"
  java_import "edu.stanford.nlp.process.CoreLabelTokenFactory"
  java_import "edu.stanford.nlp.process.WordToSentenceProcessor"
  java_import "java.io.StringReader"
end

require 'nokogiri'
require 'csv'
require 'set'
require 'optparse'




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



class SentenceSplitter
        #if %w(meta Equation Figure Table Quote Caption Abstract Reference Footnote Acknowledgement Algorithm Page Header Footer Lemma Theorem Definition Proposition URL).include?(box_type)
  def initialize(filename, options)
    @sent_id = 0
    @options = options
    parse_file(filename)
  end

  def parse_file(filename)
    puts "sentence_splitter: #{filename}" if @options[:verbose]

    # doc section para sent TAB sect label TAB sent str TAB word id range
    paragraphs = {}
    paragraph_sequence = []

    doc = File.open(filename) { |f| Nokogiri::XML(f) }
    last_para = nil
    cite = nil
    math = nil
    maths = []
    word_nodes = {}
    cites = Set.new
    doc.css('body > div.section').each do |section|
      section_id = section['id']
      section_name = section['data-name']
      section.css('> div.box').each do |box|
        box_name = box['data-name']
        box.css('> p').each do |para|
          math_para = false
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

          nodes = para.children
          if nodes[0]['data-refid']&.!=(nodes[0]['id'])
            nodes.shift
          end

          if continued_from_id
            paragraph = paragraphs[para_id] = paragraphs[continued_from_id]
            pos = paragraph.words.join.length
          else
            pos = 0
          end

          words = nodes.select { |node|
            !node['data-refid'] || node['id'] == node['data-refid']
          }.flat_map { |node|
            if node.text?
              pos += node.text.length
              next
            end
            space_val = node['data-space']
            if space_val == 'nospace' || (space_val == "bol" && pos == 0)
              space = nil
            else
              space = Word.new(nil, ' ', pos)
            end
            text = node['data-fullform'] || node.text.gsub(/\s+/, ' ')
            id = node['id']
            word_nodes[id] = node
            if cite
              math = nil
              space = nil
              word = Word.new(id, "", pos)
              cite = nil if cite == node['id']
            elsif (cite = node['data-cite-end'])
              cids = node['data-cite-id'].split(',')
              text = cids.map { |cid| "CITE-#{cid}" }.join(', ')
              cites.merge(cids)
              word = Word.new(id, text, pos)
            elsif node['data-math'] == 'B-Math' ||
                (node['data-math'] == 'I-Math' && !math) ||
                (math_para && !math)
              mid = "MATH-#{math_para ? para_id : id}"
              word = Word.new(id, mid, pos)
              math = [mid, id, id, page_id, *node['data-bdr'].split(',').map(&:to_f)]
              maths << math
            elsif node['data-math'] == 'I-Math' ||
                math_para
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
              word = Word.new(id, text, pos, node)
            end
            pos += word.to_s.length
            if space
              word.start += 1
              [space, word]
            else
              word
            end
          }.compact

          if continued_from_id
            paragraph.words += words
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
      puts sentence.inspect
      sent_id = sentence.id
      sentence.words.each do |word_id|
        word_nodes[word_id]['data-sent-id'] = sent_id
        puts word_nodes[word_id].text
      end
    end

    cites = cites.to_a.sort_by { |cite| cite.split('-')[1..-1].map(&:to_i) }

    sent_csv = CSV.generate(headers: CSV_COLS, col_sep: "\t") do |csv|
      csv << CSV_COLS
      sentences.each do |sentence|
        sentence.words = sentence.words.join(',')
        csv << sentence.values_at(*CSV_COLS_POS)
      end
    end

    math_csv = CSV.generate(col_sep: "\t") do |csv|
      csv << ['MathID', 'StartID', 'EndId', 'Page', 'X1', 'Y1', 'X2', 'Y2']
      maths.each do |row|
        csv << row
      end
    end

    cite_csv = CSV.generate(col_sep: "\t") do |csv|
      csv << ['CiteID', 'Text']
      cites.each do |cite|
        sents = paragraph_sentence_hash[cite]
        cid = "CITE-#{cite}"
        para_sents = paragraph_sentence_hash[cite]
        text =
          if para_sents
            para_sents.map(&:text).join(' ')
          else
            # cheating, since para is misclassified
            text = doc.at_css("##{cite}")['data-text']
          end
        csv << [cid, text]
      end
    end


    output = @options[:output]
    if output == "-"
      puts sent_csv
    else
      base = File.basename(filename, '.xhtml')
      output ||= File.dirname(filename)
      sentfile = File.join(output, base + '.sent.tsv')
      File.write(sentfile, sent_csv)
      mathfile = File.join(output, base + '.math.tsv')
      File.write(mathfile, math_csv)
      citefile = File.join(output, base + '.cite.tsv')
      File.write(citefile, cite_csv)
      xhtmlfile = @options[:inplace] ? filename : File.join(output, base + '.xhtml')
      File.write(xhtmlfile, doc.to_xml)
    end
  end
end

if __FILE__ == $0
  options = {}
  OptionParser.new do |opts|
    opts.banner = "Usage: #{__FILE__} [options] <file.xhtml>..."

    opts.on("-o DIR", "--output DIR", "Set output directory") do |value|
      options[:output] = value
    end
    opts.on("-i", "--[no-]in-place", "Replace the XHTML file") do |inplace|
      options[:inplace] = inplace
    end
    opts.on("-v", "--[no-]verbose", "Report file names") do |value|
      options[:verbose] = value
    end
    opts.on_tail("-h", "--help", "Show this message") do
      puts opts
      exit
    end
  end.parse!

  require_stanford_parser

  ARGV.each do |filename|
    sentence_splitter = SentenceSplitter.new(filename, options)
  end
end
