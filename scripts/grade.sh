#!/bin/bash
# Sample TTYLabBox Grader Script
# Place this at /usr/local/bin/grade.sh inside the grading VM (e.g., srv1)

# Example evaluation logic:
# if systemctl is-active httpd >/dev/null 2>&1; then
#     score=100
# else
#     score=0
# fi

# Fake output for testing:
score=100

# The application expects a JSON payload containing 'score'
echo "{"
echo "  \"score\": $score"
echo "}"
