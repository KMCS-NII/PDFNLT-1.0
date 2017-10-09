import os
import re
import itertools
import pandas as pd
import nltk
from nltk.tag import pos_tag
from nltk.tokenize import word_tokenize

from config import PATH_TO_SOURCE, PATH_TO_OUTPUT

class PathFiles(object):
    def __init__(self, source, limit=None):
        """
        `source` should be a path to a directory (as a string)
        if `limit` is set, `limit` files will be processed.
        
        Example::
            files = PathFiles(os.getcwd() + '\\corpus\\', 1000)
            
        The files in the directory should be either .tsv files or .csv files.
        """
        self.source = source
        self.limit = limit
        
        if os.path.isfile(self.source):
            self.input_files = [self.source]  # force code compatibility with list of files
        elif os.path.isdir(self.source):
            self.source = os.path.join(self.source, '')  # ensures os-specific slash at end of path
            self.input_files = os.listdir(self.source)
            self.input_files = [self.source + file for file in self.input_files]  # make full paths
            self.input_files.sort()  # makes sure it happens in filename order
        else:  # not a file or a directory, then we can't do anything with it
            raise ValueError('input is neither a file nor a path')
        
    def __iter__(self):
        for i,f in enumerate(self.input_files):
            print("[INFO]:Loading %s" % f)
            yield self.load(f)
        
    def load(self, file):
        if file[-4:] == ".tsv":
            delimiter = "\t"
        elif file[-4:] == ".csv":
            delimiter = ","
        else:
            print("[ERROR]:Failed to load %s" % f)
            assert False, "ファイルの拡張子がtsvでもcsvでもありません。"
        
        df = pd.read_csv(file, delimiter=delimiter, header = None)
        return df
    
    def pos_tag(self, df):
        df["pos"] = df[7].apply(lambda x: self.tupple2str(
            pos_tag(word_tokenize(x), tagset='universal')))
        return df
    
    def tupple2str(self, x):
        """
        Assume that x is a list of tupples such as (token, tag).
        """
        x = list(map(lambda x: x[0]+'/'+x[1], x))
        return ' '.join(x)
        
    def all_preprocess(self):
        print("[INFO]:Start preprocess")
        # 随時このリストに関数を追加していく
        todo_list = [self.pos_tag]
        print("[INFO]:Applying %s to each file." % ", ".join(list(map(lambda x: str(x).split()[2], todo_list))))
        
        for (df, file_name) in itertools.islice(zip(self, self.input_files), self.limit):
            # 各ファイルdfに対して、todo_list内の関数を全て適用していく
            
            for do in todo_list:
                df = do(df)
            output_name = re.sub(r'%s' % PATH_TO_SOURCE, PATH_TO_OUTPUT, file_name)
            df.to_csv(output_name[:-4] + ".prep" + output_name[-4:], sep='\t')
        print("[INFO]:Finished preprocess")