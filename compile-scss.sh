#!/bin/bash

# Define the directory to watch
WATCH_DIR="scss"
COMPILER_SCRIPT="compile-scss.php"
SNAPSHOT_DIR=".scss-snapshots"

# Function to compile SCSS
compile_scss() {
  local compile_type="$1"
  # echo "Compiling SCSS..."
  
  if [ -n "$compile_type" ]; then
    php "$COMPILER_SCRIPT" "$compile_type"
  else
    php "$COMPILER_SCRIPT"
  fi
  
  if [ $? -eq 0 ]; then
    echo "OK"
  else
    echo "SCSS compilation failed."
  fi
}

# Function to create snapshots
create_snapshots() {
  mkdir -p "$SNAPSHOT_DIR"
  if [ -f "$WATCH_DIR/bootstrap.scss" ]; then
    cp "$WATCH_DIR/bootstrap.scss" "$SNAPSHOT_DIR/bootstrap.scss.snapshot"
    touch -r "$WATCH_DIR/bootstrap.scss" "$SNAPSHOT_DIR/bootstrap.scss.timestamp"
  fi
  if [ -f "$WATCH_DIR/custom.scss" ]; then
    cp "$WATCH_DIR/custom.scss" "$SNAPSHOT_DIR/custom.scss.snapshot"
    touch -r "$WATCH_DIR/custom.scss" "$SNAPSHOT_DIR/custom.scss.timestamp"
  fi
  if [ -f "$WATCH_DIR/_variables.scss" ]; then
    cp "$WATCH_DIR/_variables.scss" "$SNAPSHOT_DIR/_variables.scss.snapshot"
    touch -r "$WATCH_DIR/_variables.scss" "$SNAPSHOT_DIR/_variables.scss.timestamp"
  fi
}

# Function to check if file has changed
file_has_changed() {
  local file="$1"
  local timestamp="$SNAPSHOT_DIR/$(basename "$file").timestamp"
  
  if [ -f "$timestamp" ] && [ -f "$file" ]; then
    [ "$file" -nt "$timestamp" ]
  else
    return 1
  fi
}

# Function to show diff
show_diff() {
  local file="$1"
  local snapshot="$SNAPSHOT_DIR/$(basename "$file").snapshot"
  
  if [ -f "$snapshot" ] && file_has_changed "$file"; then
    echo "📝 Changes detected in $(basename "$file"):"
    echo "----------------------------------------"
    diff -u --color "$snapshot" "$file" || true
    echo "----------------------------------------"
  fi
}

# Function to update snapshots
update_snapshots() {
  if [ -f "$WATCH_DIR/bootstrap.scss" ]; then
    cp "$WATCH_DIR/bootstrap.scss" "$SNAPSHOT_DIR/bootstrap.scss.snapshot"
    touch -r "$WATCH_DIR/bootstrap.scss" "$SNAPSHOT_DIR/bootstrap.scss.timestamp"
  fi
  if [ -f "$WATCH_DIR/custom.scss" ]; then
    cp "$WATCH_DIR/custom.scss" "$SNAPSHOT_DIR/custom.scss.snapshot"
    touch -r "$WATCH_DIR/custom.scss" "$SNAPSHOT_DIR/custom.scss.timestamp"
  fi
  if [ -f "$WATCH_DIR/_variables.scss" ]; then
    cp "$WATCH_DIR/_variables.scss" "$SNAPSHOT_DIR/_variables.scss.snapshot"
    touch -r "$WATCH_DIR/_variables.scss" "$SNAPSHOT_DIR/_variables.scss.timestamp"
  fi
}

# Function to cleanup snapshots
cleanup_snapshots() {
  if [ -d "$SNAPSHOT_DIR" ]; then
    rm -rf "$SNAPSHOT_DIR"
    echo "🧹 Cleaned up snapshot files"
  fi
}

# Function to watch for changes
watch_scss() {
  # Check if inotifywait is available
  if ! command -v inotifywait &> /dev/null; then
    echo "Error: inotifywait is not installed. Please install it (e.g., sudo apt-get install inotify-tools)."
    exit 1
  fi

  # Set up cleanup on exit
  trap cleanup_snapshots EXIT INT TERM

  # Create initial snapshots
  create_snapshots

  echo "Watching SCSS files for changes..."
  echo "Press Ctrl+C to stop watching and cleanup snapshots"
  
  while inotifywait -r -e modify,create,delete "$WATCH_DIR" 2>/dev/null; do
    # Show diffs for changed files
    for file in "$WATCH_DIR"/*.scss; do
      if [ -f "$file" ]; then
        show_diff "$file"
      fi
    done
    
    # Compile and update snapshots
    compile_scss "$COMPILE_TYPE"
    if [ $? -eq 0 ]; then
      update_snapshots
    fi
  done
}

# Function to display help message
display_help() {
  echo "Usage: ./compile-scss.sh [options]"
  echo ""
  echo "Options:"
  echo "  -w          Watch for changes and recompile automatically."
  echo "  -b          Compile only Bootstrap CSS (bootstrap.min.css)"
  echo "  -c          Compile only custom styles (custom.css)"
  echo "  -h          Display this help message."
  echo ""
  echo "By default, compiles both Bootstrap and custom styles."
  echo ""
  echo "This script generates two CSS files:"
  echo "  - bootstrap.min.css (condensed Bootstrap CSS)"
  echo "  - custom.css (expanded custom styles for client editing)"
  echo ""
  echo "Examples:"
  echo "  ./compile-scss.sh          # Compile both files"
  echo "  ./compile-scss.sh -b        # Compile only Bootstrap"
  echo "  ./compile-scss.sh -c        # Compile only custom styles"
  echo "  ./compile-scss.sh -w        # Watch and compile both (with diff output)"
  echo "  ./compile-scss.sh -w -c     # Watch and compile only custom styles (with diff output)"
  echo ""
  echo "Watch mode features:"
  echo "  - Shows git-style diff of changes when files are modified"
  echo "  - Automatically cleans up snapshot files on exit (Ctrl+C)"
}

# Parse command-line arguments
while getopts "wbhc" opt; do
  case "$opt" in
    w)
      WATCH=true
      ;;
    b)
      COMPILE_BOOTSTRAP=true
      ;;
    c)
      COMPILE_CUSTOM=true
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

# Determine what to compile
COMPILE_TYPE=""
if [ "$COMPILE_BOOTSTRAP" = true ] && [ "$COMPILE_CUSTOM" = true ]; then
  COMPILE_TYPE=""
elif [ "$COMPILE_BOOTSTRAP" = true ]; then
  COMPILE_TYPE="bootstrap"
elif [ "$COMPILE_CUSTOM" = true ]; then
  COMPILE_TYPE="custom"
fi

# Execute based on arguments
if [ "$WATCH" = true ]; then
  compile_scss "$COMPILE_TYPE"
  watch_scss
else
  compile_scss "$COMPILE_TYPE"
fi
