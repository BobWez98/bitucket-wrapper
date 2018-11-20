<?php

namespace BitbucketWrapper;

use Carbon\Carbon;
use GuzzleHttp\Client;

class Commit extends Base
{
    protected $url = 'https://api.bitbucket.org/2.0/repositories/';
    protected $client;
    public function __construct()
    {
        $this->client = new Client();
    }

    public function getPagedCommitsForRepo($repoSlug)
    {
        if (strpos($this->url, config('bitbucket.bitbucket.account')) || (strpos($this->url,
                    config('bitbucket.bitbucket.account')) && strpos($this->url, $repoSlug))) {
            $url = $this->url;
        } else {
            $url = $this->url . config('bitbucket.bitbucket.account') . '/' . $repoSlug . '/commits';
        }
        $request = $this->request($url);
        if(isset($request->next)) {
            $this->url = $request->next;
        } else {
            $this->url = '';
        }
        return $request;
    }

    public function getNextPage()
    {
        $nextPage = $this->request($this->url);
        if(isset($nextPage->next)) {
           $this->url = $nextPage->next;
        }
        return $this->url ?  $nextPage : false;
    }

    public function all($repoSlug)
    {
        $commits = [];
        while (true) {
            $pagedCommits = $this->getPagedCommitsForRepo($repoSlug);

            foreach ($pagedCommits->values as $commit) {
                $commits[] = $commit;
            }

            if (isset($pagedCommits->next)) {
                $this->url = $pagedCommits->next;
            } else {
                break;
            }
        }
        return $commits;
    }

    public function getCommitsFromDate($repoSlug, $date)
    {
        $commits = [];
        $date = Carbon::parse($date);

        while (true) {
            $pagedCommits = $this->getPagedCommitsForRepo($repoSlug);

            foreach ($pagedCommits->values as $commit) {

                if (!Carbon::parse($commit->date)->gte($date)) {
                    $break = true;
                    break;
                } else {
                    $commits[] = $commit;
                }
            }
            if (!isset($commit->next) || isset($break)) {
                break;
            } else {
                $this->url = $pagedCommits->next;
            }
        }
        return $commits;
    }

    public function getCommitsByDate($repoSlug, $date)
    {

        $date = Carbon::parse($date)->startOfDay();
        $commits = [];
        while (true) {
            $pagedCommits = $this->getPagedCommitsForRepo($repoSlug);
            foreach ($pagedCommits->values as $commit) {
                $commitDate = Carbon::parse($commit->date)->startOfDay();
                if ($date->equalTo($commitDate)) {
                    $commits[] = $commit;
                } elseif ($commitDate->lessThan($date)) {
                    $break = true;
                    break;
                }
            }

            if (isset($pagedCommits->next) && !isset($break)) {
                $this->url = $pagedCommits->next;
            } else {
                break;
            }
        }

        return $commits;
    }

    public function getCommitCountFromDate($repoSlug, $date)
    {
        return count($this->getCommitsFromDate($repoSlug, $date));
    }
}
