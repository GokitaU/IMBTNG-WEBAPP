# Generated by Django 2.0.5 on 2018-05-21 09:24

from django.db import migrations


class Migration(migrations.Migration):

    dependencies = [
        ('core', '0002_event_published'),
    ]

    operations = [
        migrations.RenameField(
            model_name='event',
            old_name='title',
            new_name='na',
        ),
    ]
