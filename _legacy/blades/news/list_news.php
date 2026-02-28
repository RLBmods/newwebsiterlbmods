<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



include_once(__DIR__ . '/../../vendor/autoload.php');
include_once(__DIR__ . '/../../db/connection.php');


                                            $sql = "SELECT * FROM news ORDER BY date DESC LIMIT 5";
                                            $result = mysqli_query($con, $sql);
                                            if(mysqli_num_rows($result) > 0) {
                                                while($row = mysqli_fetch_assoc($result)) {
                                                    echo '<div class="news-item">';
                                                    echo '<h3>' . htmlspecialchars($row['title']) . '</h3>';
                                                    echo '<p>' . htmlspecialchars($row['content']) . '</p>';
                                                    echo '<span class="news-date"> <i class="fas fa-user"></i> Author: ' . $row['author'] . '</span>';
                                                    echo '<span class="news-date"> <i class="fas fa-clock"></i>Posted:  ' . time_ago($row['date']) . '</span>';
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo '<center><h4>No news yet!</h4></center>';
                                            }

                                            function time_ago($datetime) {
                                                $time_ago = strtotime($datetime);
                                                $current_time = time();
                                                $time_difference = $current_time - $time_ago;
                                                $seconds = $time_difference;
                                                $minutes = round($seconds / 60);
                                                $hours = round($seconds / 3600);
                                                $days = round($seconds / 86400);
                                                $weeks = round($seconds / 604800);
                                                $months = round($seconds / 2592000);
                                                $years = round($seconds / 31536000);

                                                if ($seconds <= 60) {
                                                    return 'just now';
                                                } else if ($minutes <= 60) {
                                                    if ($minutes == 1) {
                                                        return '1 minute ago';
                                                    } else {
                                                        return $minutes . ' minutes ago';
                                                    }
                                                } else if ($hours <= 24) {
                                                    if ($hours == 1) {
                                                        return '1 hour ago';
                                                    } else {
                                                        return $hours . ' hours ago';
                                                    }
                                                } else if ($days <= 7) {
                                                    if ($days == 1) {
                                                        return '1 day ago';
                                                    } else {
                                                        return $days . ' days ago';
                                                    }
                                                } else if ($weeks <= 4) {
                                                    if ($weeks == 1) {
                                                        return '1 week ago';
                                                    } else {
                                                        return $weeks . ' weeks ago';
                                                    }
                                                } else if ($months <= 12) {
                                                    if ($months == 1) {
                                                        return '1 month ago';
                                                    } else {
                                                        return $months . ' months ago';
                                                    }
                                                } else {
                                                    if ($years == 1) {
                                                        return '1 year ago';
                                                    } else {
                                                        return $years . ' years ago';
                                                    }
                                                }
                                            }
                                            ?>