#!/usr/bin/env ruby

require 'cgi'
require 'nokogiri'
require 'csv'
require 'optparse'

class Iconifier
        #if %w(meta Equation Figure Table Quote Caption Abstract Reference Footnote Acknowledgement Algorithm Page Header Footer Lemma Theorem Definition Proposition URL).include?(box_type)
  def initialize(filename)
    @clip_counter = 0
    parse_file(filename)
  end

  ICON_HEIGHT = 10
  ICON_WIDTH = 10
  ICON_MARGIN = 2

  TEXT_SIZE = 15

  MARGIN_PROPORTION = 0.02
  WIDTH = 297
  HEIGHT = 400
  HEADER = 50
  MARGIN = 10

  COLORS = {
    "Equation" => "#bbd6ee",
    "Figure" => "#c4e0b2",
    "Table" => "#e4e082",
    "Reference" => "#f7caac",
  }
  BACKGROUND_COLOR = "#dddddd"
  HEADER_COLOR = "#ffff99"
  DEFAULT_COLOR = "#eeeeff"

  private def section_colour(name)
    case name
    when /related work/i
      "#ffccee"
    when /experiment/i
      "#dec599"
    else
      "#ffffff"
    end
  end

  private def area_colour(name)
    COLORS[name] || DEFAULT_COLOR
  end

  def column(areas, margin, x, y, width, right)
    areas.each_with_index do |(name, area, colour), i|
      height = area * @ratio
      @groups[name] << <<~EOF
        <rect x="#{x}" y="#{y}" width="#{width}" height="#{height}" fill="#{colour}"/>
      EOF
      @groups[name] << <<~EOF if height >= TEXT_SIZE && !(right && i == 0)
        <clipPath id="c#{@clip_counter}">
          <rect x="#{x}" y="#{y}" width="#{width}" height="#{height}"/>
        </clipPath>
        <text x="#{x}" y="#{y}" clip-path="url(#c#{@clip_counter})">#{CGI.escapeHTML(name)}</text>
      EOF
      @clip_counter += 1
      y += height + margin * @ratio
    end
  end

  def parse_file(filename)
    doc = File.open(filename) { |f| Nokogiri::XML(f) }
    @areas = Hash.new(0)
    @sections = Hash.new(0)
    section_title = nil
    doc.css('body > div.section').each do |section|
      section_name = section['data-name']
      section_title = section.css("div.box[data-name='#{section_name}Header']")&.text&.strip unless section_name.start_with?("Sub")
      section.css('> div.box').each do |box|
        box_type = box['data-name']
        @title = box.text.strip if box_type == "Title"
        area = 0
        box.css('> p').each do |para|
          x1, y1, x2, y2 = para['data-bdr'].split(?,).map(&:to_f)
          area += (x2 - x1) * (y2 - y1)
        end
        box_type = 'meta' if ['(no title)', 'meta'].include?(section_name)
        box_type = 'Body' if box_type == "#{section_name}Header" || %w(Listitem).include?(box_type)
        @title = box.text if box_type == 'Title' && !@title
        # if %w(meta Equation Figure Table Quote Caption Abstract Reference Footnote Acknowledgement Algorithm Page Header Footer Lemma Theorem Definition Proposition URL).include?(box_type)
        if %w(Equation Lemma Theorem Definition Proposition).include?(box_type)
          box_type = "Equation"
        elsif %w(Figure Algorithm).include?(box_type)
          box_type = "Figure"
        elsif %w(Abstract).include?(box_type)
          section_title = box_type
          box_type = "Body"
        elsif %w(References Reference).include?(box_type)
          box_type = "References"
        elsif %w(Footnote Caption).include?(box_type)
          box_type = "Other"
        end
        unless %w(meta Page Header Footer).include?(box_type)
          if box_type == "Body"
            @sections[section_title] += area
          else
            @areas[box_type] += area
          end
        end
      end
    end
    areas = @areas.map { |name, value| [name, value, area_colour(name)] }
    sections = @sections.map { |name, value| [name, value, section_colour(name)] }
    areas = sections + areas


    total = areas.inject(0) { |a, (_, value, _)| a + value }
    areas.delete_if { |name, value, colour| value < total / 100 }
    total = areas.inject(0) { |a, (_, value, _)| a + value }
    margin = total * MARGIN_PROPORTION
    total_with_margins = total + (areas.size - 1) * margin
    column_height = total_with_margins / 2.0
    sum = 0
    left_areas = []
    right_areas = []
    areas.each do |name, value, colour|
      sum += value + margin
      left_areas << [name, value, colour]
      break if sum > column_height
    end
    if sum - column_height > margin
      # a column is split; don't adjust margins
      right_areas << [left_areas[-1][0], sum - column_height - margin, left_areas[-1][2]]
      left_areas[-1][1] -= right_areas[-1][1]
    end
    areas[left_areas.size .. -1].each do |name, value, colour|
      right_areas << [name, value, colour]
    end
    left_total = left_areas.inject(0) { |a, (_, value, _)| a + value }
    right_total = right_areas.inject(0) { |a, (_, value, _)| a + value }
    left_margin = (column_height - left_total) / (left_areas.size - 1)
    right_margin = (column_height - right_total) / (right_areas.size - 1)
    @ratio = (HEIGHT - MARGIN * 3 - HEADER) / column_height

    @groups = Hash.new { |h, k| h[k] = [] }
    column(left_areas, left_margin, MARGIN, 2 * MARGIN + HEADER, (WIDTH - 3 * MARGIN) / 2, false)
    column(right_areas, right_margin, (WIDTH + MARGIN) / 2, 2 * MARGIN + HEADER, (WIDTH - 3 * MARGIN) / 2, true)

    groups = @groups.map { |name, areas|
      <<~EOF
        <g>
        <title>#{CGI.escapeHTML(name)}</title>
        #{areas.join.chomp}
        </g>
      EOF
    }

    @content = <<~EOF
      <?xml version="1.0" encoding="UTF-8" ?>
      <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 #{WIDTH} #{HEIGHT}" preserveAspectRatio="none" width="#{WIDTH}" height="#{HEIGHT}">
        <!-- #{filename} -->
        <style>
          text {
            font-size: #{TEXT_SIZE}px;
            alignment-baseline: hanging;
            font-family: serif;
          }
        </style>
          <rect x="" y="0" width="#{WIDTH}" height="#{HEIGHT}" fill="#{BACKGROUND_COLOR}"/>
        <g>
          <rect x="#{MARGIN}" y="#{MARGIN}" width="#{WIDTH - 2 * MARGIN}" height="#{HEADER}" fill="#{HEADER_COLOR}">
            <title>#{CGI.escapeHTML(@title || "")}</title>
          </rect>
          <clipPath id="ctitle">
            <rect x="#{MARGIN}" y="#{MARGIN}" width="#{WIDTH - 2 * MARGIN}" height="#{HEADER}"/>
          </clipPath>
          <text x="#{MARGIN}" y="#{MARGIN}" clip-path="url(#ctitle)">#{CGI.escapeHTML(@title || "")}</text>
        </g>
      #{groups.join.chomp}
      </svg>
    EOF
  end

  def to_s
    @content
  end

  def to_tsv
    CSV.generate(col_sep: "\t") do |csv|
      csv << ['Type', 'Name', 'Area']
      @sections.each do |name, area|
        csv << ["Body", name, area]
      end
      @areas.each do |name, area|
        csv << ["Special", name, area]
      end
    end
  end
end

if __FILE__ == $0
  options = {}
  OptionParser.new do |opts|
    opts.banner = "Usage: #{__FILE__} [options] <file.xhtml>..."

    opts.on("-s DIR", "--svg DIR", "Set output directory for SVG") do |value|
      options[:svg] = value
    end
    opts.on("-p DIR", "--proportions DIR", "Set output directory for proportions") do |value|
      options[:proportions] = value
    end
    opts.on("-o DIR", "--output DIR", "Set output directory") do |value|
      options[:output] = value
    end
    opts.on("-v", "--[no-]verbose", "Report file names") do |value|
      options[:verbose] = value
    end
    opts.on_tail("-h", "--help", "Show this message") do
      puts opts
      exit
    end
  end.parse!

  ARGV.each do |filename|
    puts filename if options[:verbose]
    base = File.basename(filename, '.xhtml')
    iconifier = Iconifier.new(filename)
    if ((svg_file = options[:svg] || options[:output]))
      svg_file = File.join(svg_file, base + ".area.svg") if File.directory?(svg_file)
      File.write(svg_file, iconifier.to_s)
    end
    if ((proportions_file = options[:proportions] || options[:output]))
      proportions_file = File.join(proportions_file, base + ".area.tsv") if File.directory?(proportions_file)
      File.write(proportions_file, iconifier.to_tsv)
    end
  end
end
