#!/usr/bin/env python3

import sys

print("=== Data Analysis Script ===")
print(f"Python version: {sys.version}")
print(f"Arguments received: {len(sys.argv) - 1}")

if len(sys.argv) > 1:
    print("\nArguments:")
    for i, arg in enumerate(sys.argv[1:], 1):
        print(f"  {i}. {arg}")
else:
    print("\nNo arguments provided.")

print("\nAnalysis complete!")
