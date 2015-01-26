---
#RedditBot Article
---

Make sure your bot has enough karma not to require a CAPTCHA, otherwise it won't be able to post stories etc.

- Get subscriptions ids
- Search for all comments in each subscriptions
-- Search only new comments, i.e. limit search to after date of last comment
- match comment ids that have to be responded to
- run response on each id
- if successful, store comment + response ids in data.json file
- rinse and repeat
