Prooph\Link\SqlConnector
========================
SQL connector module for [prooph LINK](https://github.com/prooph/link)

# Table Connector

The sql connector module let you read data from a table into a `Processing\Type\Type` or write data coming from a `Processing\Type\Type` into a table. Such a preconfigured table connection can be used in a workflow process either as source or target.

# TableRow and Collection Types

When connecting a table via the sql connector configurator (accessible from the dashboard) two `Processing\Type\Type` classes are created automatically. You can find them in the directory `<link root>/data/Application/DataType/SqlConnector/<your database>`. One class represents a single row of the table. It is named like the table. the other class represents a collection of table rows and is named like the table with the suffix `Collection`. The table row class contains information about the columns of the table. Columns are mapped to properties with a corresponding `Processing\Type\SingleValue` type. With the help of these auto generated types prooph/processing can handle data coming from a database table like any other processing data.

# Reading Data
to be defined ...

# Full Import
to be defined ...

# Parial Import
to be defined ...

# Insert Or Replace
to be defined ...

# Support

- Ask any questions on [prooph-users](https://groups.google.com/forum/?hl=de#!forum/prooph) google group.
- File issues at [https://github.com/prooph/link-sql-connector/issues](https://github.com/prooph/link-sql-connector/issues).

# Contribution

You wanna help us? Great!
We appreciate any help, be it on implementation level, UI improvements, testing, donation or simply trying out the system and give us feedback.
Just leave us a note in our google group linked above and we can discuss further steps.

Thanks,
your prooph team
