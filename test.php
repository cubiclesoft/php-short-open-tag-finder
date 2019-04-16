<?/* This test file demonstrates some complex parsing behaviors in a succint format.  Identical results are output regardless of whether or not short open tags exist.
  <? ?>  <-- Should be ignored.
*/?>
<?$str = '<?xml blah blah';  // Mixed open tag + XML tag in a string.
$message = str_replace('<?', '< ?', $message);  // Real example from MyBB.  Should be ignored.
?>'<?'This should still be a <?PHP string.'?>'
<?xml version="1.0" encoding="UTF-8" ?>  // This line should be ignored.
<?mso-application blah blah blah ?>  // This line should be ignored too.
<?=15*16?>  // This line should also be ignored.
<?php echo "This line should be ignored.\n"; ?>
<?PHP echo "This line should not be ignored.\n"; ?>
<?
$bla = '?> now what? <?';  // Overly contrived example from StackOverflow.  Should be ignored.
broken(  // Won't lint once fixed.  Just want the tokenizer but not syntax checking.
