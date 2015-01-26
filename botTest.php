<?php

require_once('RedditBot.php');

$b = new RedditBot();
$subNames = $b->getSubredditsNames();

function getNComments($reddit, $sub) {
  $after;
  $count = 0;
  $comments = array();

  while (count($comments) < 100) {
    if (count($comments) === 0) {
      $r = $reddit->getComments($sub[2]);
    } else {
      $r = $reddit->getComments($sub[2], $after, $count);
    }

    foreach ($r->data->children as $comment) {
      array_push($comments, [$comment->data->link_id, $comment->data->created_utc, $comment->data->body]);
    }

    $after = $r->data->after;
    $count += count($r->data->children);

  }

  return $comments;
}

var_dump(getNComments($b, $subNames));
