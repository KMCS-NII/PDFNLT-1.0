import argparse
from Preprocess import PathFiles
from config import PATH_TO_SOURCE, PATH_TO_OUTPUT

def main():
    parser = argparse.ArgumentParser(description='Preprocess papers')
    parser.add_argument('--limit', '-l', type=int, default=None,
                        help='Indicate limit of processing files if you want')
    args = parser.parse_args()
    
    files = PathFiles(PATH_TO_SOURCE, limit=args.limit)
    files.all_preprocess()
    
if __name__=='__main__':
    
    main()