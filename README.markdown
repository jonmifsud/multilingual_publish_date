Multilingual Publish Date
======================

Trying to solve the publish time problem for multiple languages


## 1 About ##

This field tries to solve the multilingual publish time problem. 

It is understandable that you wouldn't want your authors/publishers to thinker with the publication time, however you would want them to future date the articles (datetime field).
The problem is if your articles are translated at a later stage, they still take the same publication time.

There is when Multilingual Publish Date comes in, this field has two required parameters when setting up, 

1. takes the datetime field which is to be used for published time
2. The Multilingual Checkbox field which denotes publication of entry [per language]

When saving an entry; if the entry for that language has been published, the field will automatically create the correct publish date/time.
It does so using the following basics:

1. If entry not set as publish, leave value to null
2. If Multilingual Publish Date is already set, leave untouched.
3. If publish-date (datetime) is set in the future take this value
4. If publish-date not in future; take the current time

The field supports output to XML as well as sorting. At this point it does not support filtering (you should use the date-time field and publish checkbox for filtering).

## 2 Installation ##
 
1. Upload the 'multilingual_publish_date' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Field: Multilingual Publish Date", choose Enable from the with-selected menu, then click Apply.
3. The field will be available in the list when creating a Section.
