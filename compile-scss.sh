#!/bin/bash

# Define the directory to watch
WATCH_DIR="scss"
COMPILER_SCRIPT="compile-scss.php"

# Function to compile SCSS
compile_scss() {
  echo "Compiling SCSS..."
  php "$COMPILER_SCRIPT"
  if [ $? -eq 0 ]; then
    echo "OK"
  else
    echo "SCSS compilation failed."
  fi
}

# Function to watch for changes
watch_scss() {
  # Check if inotifywait is available
  if ! command -v inotifywait &> /dev/null; then
    echo "Error: inotifywait is not installed. Please install it (e.g., sudo apt-get install inotify-tools)."
    exit 1
  fi

  echo "Watching SCSS files for changes..."
  while inotifywait -r -e modify,create,delete "$WATCH_DIR" 2>/dev/null; do
    compile_scss
  done
}

# Function to display help message
display_help() {
  echo "Usage: ./compile-scss.sh [options]"
  echo ""
  echo "Options:"
  echo "  -w          Watch for changes and recompile automatically."
  echo "  -h          Display this help message."
  echo ""
  echo "By default, performs a single SCSS compilation."
}

# Parse command-line arguments
while getopts "wh" opt; do
  case "$opt" in
    w)
      WATCH=true
      ;;
    h)
      display_help
      exit 0
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      display_help
      exit 1
      ;;
  esac
done

# Remove processed options to allow other arguments to be passed
shift $((OPTIND-1))

# Execute based on arguments
if [ "$WATCH" = true ]; then
  compile_scss
  watch_scss
else
  compile_scss
fi
