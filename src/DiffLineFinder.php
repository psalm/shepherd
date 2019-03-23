<?php

namespace Psalm\Spirit;

class DiffLineFinder
{
	public static function getGitHubPositionFromDiff(
        int $input_line,
        string $file_name,
        string $diff_string
    ) : ?int {
		$diff_parser = new \SebastianBergmann\Diff\Parser();
        $diffs = $diff_parser->parse($diff_string);

		foreach ($diffs as $diff) {
            if ($diff->getTo() === 'b/' . $file_name) {
                $diff_file_offset = 0;

                foreach ($diff->getChunks() as $chunk) {
                    $chunk_end = $chunk->getEnd();
                    $chunk_end_range = $chunk->getEndRange();

                    if ($input_line >= $chunk_end
                        && $input_line < $chunk_end + $chunk_end_range
                    ) {
                        $line_offset = 0;
                        foreach ($chunk->getLines() as $i => $chunk_line) {
                            $diff_file_offset++;

                            if ($chunk_line->getType() !== \SebastianBergmann\Diff\Line::REMOVED) {
                                $line_offset++;
                            }

                            if ($input_line === $line_offset + $chunk_end - 1) {
                                return $diff_file_offset;
                            }
                        }
                    } else {
                        $diff_file_offset += count($chunk->getLines());
                    }

                    $diff_file_offset++;
                }
            }
        }

        return null;
	}
}