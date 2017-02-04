
# GNSS conversion tools
Easy GNSS coordinates/time conversions via an HTTP API.


This is a legacy project, in memoriam of GPLUS :purple_heart:


# Supported reference frames
  - IGb00
  - IGb08
  - IGS97
  - IGS00
  - IGS05
  - IGS08
  - ITRF88
  - ITRF89
  - ITRF90
  - ITRF91
  - ITRF92
  - ITRF93
  - ITRF94
  - ITRF96
  - ITRF97
  - ITRF00
  - ITRF05

# Installation
The following instructions are valid for an Ubuntu setup, but any Linux host is OK.

First, install Apache2 and PHP:

    sudo apt-get install apache2 php

Clone the source code under the public documents folder of Apache:

    cd /var/www
    git clone https://github.com/csparpa/gnss-conversion-tools

Then restart your webserver with

    sudo service apache2 restart

You should be able now to reach the PHP handlers under the base URL:

    http://localhost/gnss-conversion-tools/api


# Scientific references
The actual calculations are performed by the PHP libraries in:

    api/lib

For a scientific reference please read:

    html/transformations-detail.html

The parameters used in the transformations are stored in:

    TRF_transf.TP


# Usage

## Lookup the first epoch for a given reference frame
GET http://localhost/gnss-conversion-tools/api/default-start-epoch-for.php?reference_frame=IGS97

## List reference frames
GET http://localhost/gnss-conversion-tools/api/list-reference-frames.php

## GPS dates conversion
POST http://localhost/gnss-conversion-tools/api/gps-date-converter.php

    POST data:
        date -->  any of the following formats:
                     MJD format
                     GSPTime
                     Year and DOY (YYYY.DDD)
                     Year and decimal (YYYY.xxxx)
                     Year month and day (YYMMDD)

Example response:

    {
      "mjd_output": "57754.0",
      "GPSTime_output": "1930 0",
      "year_doy_output": "2017 001",
      "decimal_output": "2017.0002",
      "YYMMDD_output": "2017 01 01",
      "message": ""
    }

## GNSS coordinates transformation
The user must specify the following POST parameters:
  - type of coordinates: geodetic (ellipsoidal) or cartesian
  - Earth-Centered Earth-Fixed Cartesian/Ellipsoidal coordinates triplet ("X coord", "Y coord" and "Z coord")
  - Earth-Centered Earth-Fixed Cartesian/Ellipsoidal velocity triplet ("X vel", "Y vel" and "Z vel")
  - Reference frame the input coordinates and velocities are given
  - Reference epoch ("year" + "DoY" + "SoD") the input coordinates and velocities are referred to

and the following target parameters:

  - target reference frame
  - target epoch ("year" + "DoY" + "SoD") for propagating the input coordinates (based on a linear model)

The tool calculates as output:
  - the resulting Earth-Centered Earth-Fixed Cartesian coordinates in the chosen reference frame
  - the resulting Earth-Centered Earth-Fixed Ellipsoidal coordinates in the chosen reference frame

Example:
POST http://localhost/gnss-conversion-tools/api/gnss-coordinates-transformation.php

POST data:

    type --> geodetic | cartesian
    start_ref_frame
    x_coord
    y_coord
    z_coord
    x_vel
    y_vel
    z_vel
    start_year
    start_doy --> DDD
    start_sod
    end_ref_frame
    end_year
    end_doy --> DDD
    end_sod


# Web page (graphical user interface)
Coming soon...

## Usage
How to use the tools:

    html/usage.html
