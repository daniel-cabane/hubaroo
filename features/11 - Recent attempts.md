## Feature rework

# Main idea
Make the Recent attempts, which currently lives in the home page, site-wide

# Implementation
Take the code from the 'Recent Attempts Overlay (Bottom)' from line 115 on the home-view.vue and turn it into a site-wide component (insert it in App.vue).
The component should take the form of a small icon stuck to the left side of the page (fixed position). When the icon is clicked, a div slides in from the left side, displaying the last 3 attempts (vertical layout).
The component should display as an overlay on all pages, except the attempt page (when the student is taking the test).
If there is no recent attempts, the component is hidden entirely

