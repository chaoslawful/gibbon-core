#!/usr/bin/env perl
# Script to deduplicate location references in .po files
# Handles file paths with spaces correctly
# Can replace spaces with underscores for poedit compatibility
# Usage: perl deduplicate_po_locations.pl <input.po> [output.po] [--replace-spaces]

use strict;
use warnings;

my $input_file = $ARGV[0] || die "Usage: $0 <input.po> [output.po] [--replace-spaces]\n";
my $output_file = $ARGV[1] || $input_file;
my $replace_spaces = 0;

# Check for --replace-spaces flag
if (grep { $_ eq '--replace-spaces' } @ARGV) {
    $replace_spaces = 1;
    # Remove the flag from ARGV
    @ARGV = grep { $_ ne '--replace-spaces' } @ARGV;
    # Adjust output file if it was the flag
    if ($#ARGV >= 1) {
        $output_file = $ARGV[1];
    } elsif ($#ARGV == 0) {
        $output_file = $input_file;
    }
}

open(my $in_fh, '<', $input_file) or die "Cannot open input file: $!\n";
open(my $out_fh, '>', $output_file) or die "Cannot open output file: $!\n";

my $line;
while (defined($line = <$in_fh>)) {
    if ($line =~ /^#: (.+)$/) {
        my $locations_str = $1;
        
        # Parse locations correctly handling spaces in file paths
        # Format: filepath:line filepath:line ...
        # The key insight: each location ends with :number, and locations are separated by spaces
        # But file paths can contain spaces, so we need to identify where one location ends
        # and the next begins by finding ":number" patterns
        
        my @locations = ();
        my $remaining = $locations_str;
        
        # Match pattern: anything followed by :number (where number is followed by space or end)
        # We'll match greedily from the start, but stop at ":number " or ":number$"
        while ($remaining =~ /^(.+?):(\d+)(?:\s+|$)/) {
            my $filepath = $1;
            my $line_num = $2;
            push @locations, "$filepath:$line_num";
            
            # Remove matched portion from remaining string
            $remaining = $';
            $remaining =~ s/^\s+//; # Remove leading spaces
        }
        
        # If there's still remaining text that wasn't matched, it might be a malformed entry
        # Try to salvage it by treating the whole thing as one location if it ends with :number
        if ($remaining =~ /^(.+?):(\d+)$/) {
            push @locations, "$1:$2";
        }
        
        # Deduplicate locations
        my %seen;
        my @unique_locations;
        foreach my $loc (@locations) {
            if (!exists $seen{$loc}) {
                $seen{$loc} = 1;
                push @unique_locations, $loc;
            }
        }

        # Quote spaces with U+2068 and U+2069 if requested (for PO format compatibility)
        if ($replace_spaces) {
            @unique_locations = map {
                # Quote spaces in file path but keep the line number
                if (/^(.+?):(\d+)$/) {
                    my $filepath = $1;
                    my $line_num = $2;
                    # Wrap consecutive spaces with U+2068 (FSI) and U+2069 (PDI)
                    $filepath =~ s/([ ]+)/\x{2068}$1\x{2069}/g;
                    "$filepath:$line_num";
                } else {
                    $_;
                }
            } @unique_locations;
        }

        # Write deduplicated locations
        if (@unique_locations > 0) {
            print $out_fh "#: " . join(" ", @unique_locations) . "\n";
        }
    } else {
        # Write other lines as-is
        print $out_fh $line;
    }
}

close($in_fh);
close($out_fh);

print "Deduplication complete. Output written to: $output_file\n";

